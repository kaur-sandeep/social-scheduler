<?php

namespace App\Services\Social;

use App\Models\SocialPage;
use App\Repositories\SocialAccountRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use RuntimeException;

class MetaAnalyticsService
{
    private string $base;

    public function __construct(private readonly SocialAccountRepository $accounts) { $this->base = 'https://graph.facebook.com/'.config('facebook.graph_version'); }

    public function dashboard(SocialPage $page, string $platform, string $since, string $until): array
    {
        return $this->cachedDashboard($page, $platform, $since, $until, true);
    }

    /** Fetch account-level metrics and post dates for a summary without N per-post insight requests. */
    public function dashboardForDailyReport(SocialPage $page, string $platform, string $since, string $until): array
    {
        return $this->cachedDashboard($page, $platform, $since, $until, false, true);
    }

    /**
     * Count published content from Meta directly. Facebook uses edge summaries when
     * available; paginated metadata-only reads are the fallback. Instagram's total
     * comes from media_count, while date counts use its media edge and timestamps.
     */
    public function publishedPostCounts(
        SocialPage $page,
        string $platform,
        string $since,
        string $until,
        string $day,
        string $timezone,
        mixed $instagramMediaCount = null,
    ): array {
        $id = $platform === 'instagram' ? $page->instagram_business_id : $page->page_id;
        $cacheKey = 'meta-published-counts-v2:'.hash('sha256', implode('|', [
            $page->id, $platform, $since, $until, $day, $id, $instagramMediaCount, hash('sha256', (string) $page->page_access_token),
        ]));

        try {
            return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($page, $platform, $since, $until, $day, $timezone, $instagramMediaCount, $id) {
                if (! $id || ! $page->page_access_token || $page->status !== 'active') {
                    return ['total' => 0, 'day' => 0, 'period' => 0];
                }

                if ($platform === 'facebook') {
                    return [
                        'total' => $this->facebookPublishedCount($id, $page->page_access_token),
                        'day' => $this->facebookPublishedCount($id, $page->page_access_token, $day, $day, $timezone),
                        'period' => $this->facebookPublishedCount($id, $page->page_access_token, $since, $until, $timezone),
                    ];
                }

                $total = is_numeric($instagramMediaCount)
                    ? (int) $instagramMediaCount
                    : (int) data_get($this->get('/'.$id, ['fields' => 'id,media_count', 'access_token' => $page->page_access_token], true), 'media_count', 0);
                $startDate = min($since, $day);
                $endDate = max($until, $day);
                $counts = $this->instagramMediaCounts($id, $page->page_access_token, $startDate, $endDate, $since, $until, $day, $timezone);
                return ['total' => $total, 'day' => $counts['day'], 'period' => $counts['period']];
            });
        } catch (\Throwable $exception) {
            Log::warning('Meta published post counts could not be retrieved; using zero counts.', [
                'social_page_id' => $page->id,
                'page_id' => $id,
                'platform' => $platform,
                'since' => $since,
                'until' => $until,
                'message' => $exception->getMessage(),
            ]);
            return ['total' => 0, 'day' => 0, 'period' => 0];
        }
    }

    private function facebookPublishedCount(string $pageId, string $token, ?string $since = null, ?string $until = null, string $timezone = 'UTC'): int
    {
        $query = ['fields' => 'id', 'limit' => 1, 'summary' => 'total_count', 'access_token' => $token];
        // Meta requires a positive `since` for published_posts summary counts.
        $query['since'] = $since
            ? Carbon::parse($since, $timezone)->startOfDay()->timestamp
            : 1;
        if ($until) $query['until'] = Carbon::parse($until, $timezone)->endOfDay()->timestamp;

        $summary = $this->get('/'.$pageId.'/published_posts', $query, true);
        $summaryCount = data_get($summary, 'summary.total_count');
        // Meta's published_posts summary can report only the first 25 items for some
        // Pages (notably where newer video/Reel content is present). Treat that value
        // as a hint, then paginate metadata to get the complete count.
        if (is_numeric($summaryCount) && (int) $summaryCount < 25) return (int) $summaryCount;

        $fallbackQuery = ['fields' => 'id,created_time', 'limit' => 100, 'access_token' => $token];
        // Keep the required positive lower bound for lifetime queries too. Meta may
        // reject or return an empty edge when the summary request has `since=1` but
        // the paginated fallback omits it.
        $fallbackQuery['since'] = $since
            ? Carbon::parse($since, $timezone)->startOfDay()->timestamp
            : 1;
        if ($until) $fallbackQuery['until'] = Carbon::parse($until, $timezone)->endOfDay()->timestamp;
        $paginatedCount = $this->countPagedMedia('/'.$pageId.'/published_posts', $fallbackQuery, $since, $until, $timezone);
        if ($paginatedCount === null) return is_numeric($summaryCount) ? (int) $summaryCount : 0;

        // Keep Meta's nonzero summary as a floor if the edge only returns a partial
        // first page; a successful pagination result can still raise the count above
        // a summary capped at 25.
        return max(is_numeric($summaryCount) ? (int) $summaryCount : 0, $paginatedCount);
    }

    private function instagramMediaCounts(string $instagramId, string $token, string $requestSince, string $requestUntil, string $countSince, string $countUntil, string $day, string $timezone): array
    {
        $from = Carbon::parse($requestSince, $timezone)->startOfDay();
        $to = Carbon::parse($requestUntil, $timezone)->endOfDay();
        $query = [
            'fields' => 'id,timestamp',
            'limit' => 100,
            'since' => $from->timestamp,
            'until' => $to->timestamp,
            'access_token' => $token,
        ];
        $media = $this->collectPagedMedia('/'.$instagramId.'/media', $query);
        if ($media === null) {
            // If the endpoint does not accept time filters for this account/version,
            // paginate only IDs and timestamps and apply the same local date bounds.
            $media = $this->collectPagedMedia('/'.$instagramId.'/media', [
                'fields' => 'id,timestamp', 'limit' => 100, 'access_token' => $token,
            ]) ?? [];
        }

        $dayCount = 0;
        $periodCount = 0;
        foreach ($media as $item) {
            if (empty($item['timestamp']) || empty($item['id'])) continue;
            $date = Carbon::parse($item['timestamp'])->setTimezone($timezone)->toDateString();
            if ($date === $day) $dayCount++;
            if ($date >= $countSince && $date <= $countUntil) $periodCount++;
        }
        return ['day' => $dayCount, 'period' => $periodCount];
    }

    private function countPagedMedia(string $path, array $query, ?string $since, ?string $until, string $timezone): ?int
    {
        $items = $this->collectPagedMedia($path, $query);
        if ($items === null) return null;
        if (! $since || ! $until) return count($items);
        return count(array_filter($items, function (array $item) use ($since, $until, $timezone): bool {
            if (empty($item['created_time'])) return false;
            $date = Carbon::parse($item['created_time'])->setTimezone($timezone)->toDateString();
            return $date >= $since && $date <= $until;
        }));
    }

    /** Metadata-only pagination; never requests or downloads media URLs. */
    private function collectPagedMedia(string $path, array $query): ?array
    {
        $payload = $this->get($path, $query, true);
        if ($payload['_analytics_error'] ?? false) return null;
        $items = [];
        $seen = [];
        while (true) {
            foreach ($payload['data'] ?? [] as $item) {
                if (! empty($item['id'])) $items[(string) $item['id']] = $item;
            }
            $next = data_get($payload, 'paging.next');
            if (! $next) break;
            if (isset($seen[$next]) || ! $this->isMetaPagingUrl($next)) {
                Log::warning('Meta published post pagination returned an invalid next URL.');
                return null;
            }
            $seen[$next] = true;
            $response = Http::timeout(25)->get($next);
            if ($response->failed()) {
                Log::warning('Meta published post pagination failed.', [
                    'status' => $response->status(),
                    'message' => data_get($response->json(), 'error.message'),
                ]);
                return null;
            }
            $payload = $response->json() ?? [];
        }
        return array_values($items);
    }

    private function isMetaPagingUrl(string $url): bool
    {
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? null) === 'https'
            && strtolower($parsed['host'] ?? '') === 'graph.facebook.com'
            && str_starts_with($parsed['path'] ?? '', '/'.config('facebook.graph_version').'/');
    }

    private function cachedDashboard(SocialPage $page, string $platform, string $since, string $until, bool $includeContentInsights, bool $includePostMetrics = false): array
    {
        $cacheKey = 'meta-analytics-v6:'.hash('sha256', implode('|', [$page->id, $platform, $since, $until, $includeContentInsights ? 'full' : 'summary', $includePostMetrics ? 'post-metrics' : 'no-post-metrics', $page->page_access_token]));
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($page, $platform, $since, $until, $includeContentInsights, $includePostMetrics) {
            try {
                return $this->fetchDashboard($page, $platform, $since, $until, $includeContentInsights, $includePostMetrics);
            } catch (RuntimeException $exception) {
                if (! $this->isPagePermissionError($exception) || ! $this->refreshPageAccessToken($page)) {
                    throw $exception;
                }

                // A newly authorized page can leave older saved Page tokens stale.
                return $this->fetchDashboard($page->fresh(), $platform, $since, $until, $includeContentInsights, $includePostMetrics);
            }
        });
    }

    private function isPagePermissionError(RuntimeException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'before impersonating a user')
            || (str_contains($message, 'permission') && str_contains($message, 'page'));
    }

    /** Reissue a Page token from the connected user's currently authorized Page list. */
    private function refreshPageAccessToken(SocialPage $page): bool
    {
        $account = $page->account;
        $userToken = $account?->user_access_token;
        if (! $userToken) return false;

        $response = Http::timeout(30)->get($this->base.'/me/accounts', [
            'fields' => 'id,access_token,tasks',
            'limit' => 100,
            'access_token' => $userToken,
        ]);
        if ($response->failed()) {
            Log::notice('Could not refresh a Meta Page token from the connected user account.', [
                'social_page_id' => $page->id,
                'status' => $response->status(),
                'message' => data_get($response->json(), 'error.message'),
            ]);
            return false;
        }

        $authorizedPage = collect($response->json('data', []))->firstWhere('id', $page->page_id);
        if (! is_array($authorizedPage) || empty($authorizedPage['access_token'])) return false;

        $this->accounts->upsertPage(
            ['social_account_id' => $page->social_account_id, 'provider' => 'facebook', 'page_id' => $page->page_id],
            ['page_access_token' => $authorizedPage['access_token'], 'permissions' => $authorizedPage['tasks'] ?? $page->permissions]
        );

        return true;
    }

    private function fetchDashboard(SocialPage $page, string $platform, string $since, string $until, bool $includeContentInsights = true, bool $includePostMetrics = false): array
    {
        $ig = $platform === 'instagram';
        $id = $ig ? $page->instagram_business_id : $page->page_id;
        if (! $id || $page->status !== 'active') throw new RuntimeException('This professional account is not connected.');
        $token = $page->page_access_token;
        $account = $this->get('/'.$id, ['fields' => $ig ? 'id,username,media_count,followers_count' : 'id,name,fan_count', 'access_token' => $token]);
        if (! $ig) {
            $followerAccount = $this->get('/'.$id, ['fields' => 'followers_count', 'access_token' => $token], true);
            if (! ($followerAccount['_analytics_error'] ?? false) && array_key_exists('followers_count', $followerAccount)) {
                $account['followers_count'] = $followerAccount['followers_count'];
            }
        }
        // Request metrics independently: Meta can reject a whole batch when one metric is
        // unsupported for the account, token, or pinned Graph API version.
        $insightsSince = $since;
        $insightsWindowLimited = false;
        if ($ig) {
            $insightFloor = Carbon::parse($until)->subDays(89)->toDateString();
            if ($since < $insightFloor) {
                $insightsSince = $insightFloor;
                $insightsWindowLimited = true;
            }
        }
        $metricQueries = $ig
            ? [
                ['views', 'day', $includeContentInsights ? 'total_value' : 'time_series'],
                ['reach', 'day', 'time_series'],
                ...($includeContentInsights ? [['accounts_engaged', 'day', 'total_value'], ['follower_count', 'lifetime', 'total_value']] : []),
            ]
            : array_map(fn ($name) => [$name, 'day', null], $includeContentInsights
                ? ['page_daily_follows', 'page_daily_unfollows', 'page_media_view', 'page_total_media_view_unique', 'page_post_engagements']
                : ['page_daily_follows', 'page_daily_unfollows', 'page_media_view', 'page_total_media_view_unique']);
        $insightRows = [];
        $unavailableInsights = [];
        foreach ($metricQueries as [$metricName, $period, $metricType]) {
            $windows = $metricName === 'follower_count' ? [[null, null]] : $this->dateWindows($insightsSince, $until);
            $metricRow = null;
            foreach ($windows as [$windowSince, $windowUntil]) {
                $query = ['metric' => $metricName, 'period' => $period, 'access_token' => $token];
                if ($windowSince !== null) $query += ['since' => $windowSince, 'until' => $windowUntil];
                if ($metricType) $query['metric_type'] = $metricType;
                $response = $this->get('/'.$id.'/insights', $query, true);
                if ($response['_analytics_error'] ?? false) {
                    $unavailableInsights[] = $metricName;
                    continue;
                }
                foreach ($this->normalizeInsights($response['data'] ?? [], $windowUntil ?? $until) as $row) {
                    $metricRow ??= $row;
                    $metricRow['values'] = array_merge($metricRow['values'] ?? [], $row['values'] ?? []);
                }
            }
            if ($metricRow) {
                $metricRow['values'] = collect($metricRow['values'] ?? [])->unique('end_time')->sortBy('end_time')->values()->all();
                $insightRows[] = $metricRow;
            }
        }
        // Facebook's Page demographic insights are no longer available. Instagram
        // may return follower demographics for eligible professional accounts.
        $demographics = [];
        if ($ig && $includeContentInsights) {
            $rawDemographics = $this->get('/'.$id.'/insights', [
                'metric' => 'follower_demographics', 'period' => 'lifetime',
                'metric_type' => 'total_value', 'breakdown' => 'age,gender',
                'access_token' => $token,
            ], true);
            if (! ($rawDemographics['_analytics_error'] ?? false)) {
                $demographics = $rawDemographics['data'] ?? [];
            }
        }
        // Meta interprets date-only `until` values at midnight, which can exclude
        // every post published later on the final day. Send full-day Unix bounds
        // so the report includes posts through 23:59:59 in the app's timezone.
        $timezone = config('social_reports.timezone', config('app.timezone', 'UTC'));
        $mediaSince = Carbon::parse($since, $timezone)->startOfDay()->timestamp;
        $mediaUntil = Carbon::parse($until, $timezone)->endOfDay()->timestamp;
        $igFields = $includeContentInsights ? 'id,caption,media_type,media_product_type,timestamp,permalink,thumbnail_url,media_url,like_count,comments_count' : ($includePostMetrics ? 'id,media_type,media_product_type,timestamp,like_count,comments_count' : 'id,media_type,media_product_type,timestamp');
        $fbFields = ($includeContentInsights || $includePostMetrics) ? 'id,message,status_type,created_time,permalink_url,full_picture,shares,comments.limit(0).summary(true),reactions.limit(0).summary(true)' : 'id,status_type,created_time';
        $mediaPath = '/'.$id.($ig ? '/media' : '/published_posts');
        $mediaQuery = ['fields' => $ig ? $igFields : $fbFields, 'since' => $mediaSince, 'until' => $mediaUntil, 'limit' => 100, 'access_token' => $token];
        $mediaUnavailable = false;
        if ($ig || $includePostMetrics) {
            $collected = $this->collectPagedMedia($mediaPath, $mediaQuery);
            $mediaUnavailable = $ig && $collected === null;
            $mediaItems = $collected ?? [];
        } else {
            $mediaItems = $this->get($mediaPath, $mediaQuery)['data'] ?? [];
        }
        $items = [];
        $mediaInsightsUnavailable = [];
        foreach ($mediaItems as $item) {
            $metrics = [];
            if ($ig && ($includeContentInsights || $includePostMetrics)) {
                $names = ['views', 'reach', 'saved'];
                $mediaType = strtoupper((string) ($item['media_product_type'] ?? $item['media_type'] ?? ''));
                if (in_array($mediaType, ['REEL', 'VIDEO'], true) || strtoupper((string) ($item['media_type'] ?? '')) === 'VIDEO') $names[] = 'shares';
                foreach ($names as $metricName) {
                    $raw = $this->get('/'.$item['id'].'/insights', ['metric' => $metricName, 'metric_type' => 'total_value', 'access_token' => $token], true);
                    if ($raw['_analytics_error'] ?? false) { $mediaInsightsUnavailable[] = ['media_id' => $item['id'], 'metric' => $metricName]; continue; }
                    $found = false;
                    foreach ($raw['data'] ?? [] as $m) if (($m['name'] ?? null) === $metricName) { $metrics[$m['name']] = $this->metricValue($m); $found = is_numeric($metrics[$m['name']]); }
                    if (! $found) $mediaInsightsUnavailable[] = ['media_id' => $item['id'], 'metric' => $metricName];
                }
                $metrics += ['likes' => $item['like_count'] ?? null, 'comments' => $item['comments_count'] ?? null];
            } elseif (! $ig && ($includeContentInsights || $includePostMetrics)) {
                $metrics = ['reactions' => data_get($item, 'reactions.summary.total_count'), 'comments' => data_get($item, 'comments.summary.total_count'), 'shares' => data_get($item, 'shares.count')];
                if ($includeContentInsights || $includePostMetrics) {
                    try { $raw = $this->get('/'.$item['id'].'/insights', ['metric' => 'post_media_view', 'access_token' => $token], true); foreach ($raw['data'] ?? [] as $m) $metrics[$m['name']] = $this->metricValue($m); } catch (RuntimeException) {}
                }
            }
            $items[] = ['id' => $item['id'], 'text' => $item['caption'] ?? $item['message'] ?? '', 'published_at' => $item['timestamp'] ?? $item['created_time'] ?? null, 'url' => $item['permalink'] ?? $item['permalink_url'] ?? null, 'thumbnail' => $item['thumbnail_url'] ?? $item['media_url'] ?? $item['full_picture'] ?? null, 'type' => $item['media_product_type'] ?? $item['media_type'] ?? $item['status_type'] ?? null, 'metrics' => $metrics];
        }
        return ['account' => $account, 'insights' => $insightRows, 'media' => $items, 'demographics' => $demographics, 'insights_since' => $insightsSince, 'insights_until' => $until, 'insights_window_limited' => $insightsWindowLimited, 'insights_unavailable' => $unavailableInsights, 'media_unavailable' => $mediaUnavailable, 'media_insights_unavailable' => $mediaInsightsUnavailable];
    }

    public function postMetrics(SocialPage $page, string $platform, string $postId): array
    {
        if ($page->status !== 'active' || ! $page->page_access_token) throw new RuntimeException('The social account is disconnected.');
        $ig = $platform === 'instagram';
        if ($ig) {
            if (! $page->instagram_business_id) throw new RuntimeException('Instagram Professional account is not connected.');
            $media = $this->get('/'.$postId, ['fields' => 'id,media_type,media_product_type,like_count,comments_count', 'access_token' => $page->page_access_token]);
            $type = $media['media_product_type'] ?? $media['media_type'] ?? 'IMAGE';
            $metrics = ['reach', 'views', 'likes', 'comments', 'saved', 'shares'];
            $raw = $this->get('/'.$postId.'/insights', ['metric' => implode(',', $metrics), 'metric_type' => 'total_value', 'access_token' => $page->page_access_token], true);
            $values = [];
            foreach ($raw['data'] ?? [] as $metric) $values[$metric['name']] = $this->metricValue($metric);
            $values['likes'] ??= $media['like_count'] ?? null;
            $values['comments'] ??= $media['comments_count'] ?? null;
            return $values;
        }
        if ($page->provider !== 'facebook') throw new RuntimeException('This post is not a Facebook post.');
        $post = $this->get('/'.$postId, ['fields' => 'id,reactions.limit(0).summary(true),comments.limit(0).summary(true),shares', 'access_token' => $page->page_access_token]);
        $values = ['reactions' => data_get($post, 'reactions.summary.total_count'), 'comments' => data_get($post, 'comments.summary.total_count'), 'shares' => data_get($post, 'shares.count')];
        $raw = $this->get('/'.$postId.'/insights', ['metric' => 'post_media_view', 'access_token' => $page->page_access_token], true);
        foreach ($raw['data'] ?? [] as $metric) $values[$metric['name']] = $this->metricValue($metric);
        return $values;
    }

    private function get(string $path, array $query, bool $optional = false): array
    {
        $response = Http::timeout(25)->get($this->base.$path, $query);
        if ($response->failed()) {
            if ($optional) {
                Log::notice('Optional Meta analytics request was unavailable.', ['path' => $path, 'status' => $response->status(), 'message' => data_get($response->json(), 'error.message')]);
                return ['_analytics_error' => true];
            }
            throw new RuntimeException(data_get($response->json(), 'error.message', 'Meta analytics request failed (HTTP '.$response->status().').'));
        }
        return $response->json() ?? [];
    }

    private function normalizeInsights(array $metrics, string $until): array
    {
        return array_map(function (array $metric) use ($until): array {
            if (! isset($metric['values']) && isset($metric['total_value']['value'])) {
                $metric['values'] = [['value' => $metric['total_value']['value'], 'end_time' => $until]];
                $metric['_analytics_total_only'] = true;
            } elseif (! isset($metric['values']) && array_key_exists('value', $metric)) {
                $metric['values'] = [['value' => $metric['value'], 'end_time' => $until]];
                $metric['_analytics_total_only'] = true;
            }
            return $metric;
        }, $metrics);
    }

    /** Split Page Insights requests into Meta's maximum 90-day query windows. */
    private function dateWindows(string $since, string $until): array
    {
        $windows = [];
        $cursor = Carbon::parse($since)->startOfDay();
        $end = Carbon::parse($until)->startOfDay();
        if ($cursor->equalTo($end)) {
            return [[$cursor->toDateString(), $end->copy()->addDay()->toDateString()]];
        }
        while ($cursor->lte($end)) {
            $windowEnd = $cursor->copy()->addDays(89);
            if ($windowEnd->gt($end)) $windowEnd = $end->copy();
            // Treat the requested end date as inclusive and send the next
            // midnight as Meta's exclusive `until` boundary.
            $windows[] = [$cursor->toDateString(), $windowEnd->copy()->addDay()->toDateString()];
            $cursor = $windowEnd->copy()->addDay();
        }
        return $windows;
    }

    private function metricValue(array $metric): mixed
    {
        return data_get($metric, 'total_value.value', data_get($metric, 'values.0.value', $metric['value'] ?? null));
    }
}
