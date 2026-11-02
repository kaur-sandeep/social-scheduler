<?php

namespace App\Services\Social;

use App\Models\SocialPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use RuntimeException;

class MetaAnalyticsService
{
    private string $base;

    public function __construct() { $this->base = 'https://graph.facebook.com/'.config('facebook.graph_version'); }

    public function dashboard(SocialPage $page, string $platform, string $since, string $until): array
    {
        $cacheKey = 'meta-analytics:'.hash('sha256', implode('|', [$page->id, $platform, $since, $until, $page->page_access_token]));
        return Cache::remember($cacheKey, now()->addMinutes(5), fn () => $this->fetchDashboard($page, $platform, $since, $until));
    }

    private function fetchDashboard(SocialPage $page, string $platform, string $since, string $until): array
    {
        $ig = $platform === 'instagram';
        $id = $ig ? $page->instagram_business_id : $page->page_id;
        if (! $id || $page->status !== 'active') throw new RuntimeException('This professional account is not connected.');
        $token = $page->page_access_token;
        $account = $this->get('/'.$id, ['fields' => $ig ? 'id,username,media_count,followers_count' : 'id,name,fan_count', 'access_token' => $token]);
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
                ['views', 'day', 'total_value'],
                ['reach', 'day', 'time_series'],
                ['accounts_engaged', 'day', 'total_value'],
                ['follower_count', 'lifetime', 'total_value'],
            ]
            : array_map(fn ($name) => [$name, 'day', null], ['page_follows', 'page_media_view', 'page_post_engagements']);
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
        // Graph API rejects an identical since/until pair. Use the next midnight as
        // the exclusive end boundary when the selected report is a single day.
        $mediaUntil = $since === $until ? Carbon::parse($until)->addDay()->toDateString() : $until;
        $media = $this->get('/'.$id.($ig ? '/media' : '/published_posts'), ['fields' => $ig ? 'id,caption,media_type,media_product_type,timestamp,permalink,thumbnail_url,media_url,like_count,comments_count' : 'id,message,created_time,permalink_url,full_picture,shares,comments.limit(0).summary(true),reactions.limit(0).summary(true)', 'since' => $since, 'until' => $mediaUntil, 'limit' => 100, 'access_token' => $token]);
        $items = [];
        foreach ($media['data'] ?? [] as $item) {
            $metrics = [];
            if ($ig) {
                $names = 'views,reach,saved,shares';
                try { $raw = $this->get('/'.$item['id'].'/insights', ['metric' => $names, 'metric_type' => 'total_value', 'access_token' => $token], true); foreach ($raw['data'] ?? [] as $m) $metrics[$m['name']] = $this->metricValue($m); } catch (RuntimeException) {}
                $metrics += ['likes' => $item['like_count'] ?? null, 'comments' => $item['comments_count'] ?? null];
            } else {
                $metrics = ['reactions' => data_get($item, 'reactions.summary.total_count'), 'comments' => data_get($item, 'comments.summary.total_count'), 'shares' => data_get($item, 'shares.count')];
                try { $raw = $this->get('/'.$item['id'].'/insights', ['metric' => 'post_media_view', 'access_token' => $token], true); foreach ($raw['data'] ?? [] as $m) $metrics[$m['name']] = $this->metricValue($m); } catch (RuntimeException) {}
            }
            $items[] = ['id' => $item['id'], 'text' => $item['caption'] ?? $item['message'] ?? '', 'published_at' => $item['timestamp'] ?? $item['created_time'] ?? null, 'url' => $item['permalink'] ?? $item['permalink_url'] ?? null, 'thumbnail' => $item['thumbnail_url'] ?? $item['media_url'] ?? $item['full_picture'] ?? null, 'type' => $item['media_product_type'] ?? $item['media_type'] ?? null, 'metrics' => $metrics];
        }
        return ['account' => $account, 'insights' => $insightRows, 'media' => $items, 'insights_since' => $insightsSince, 'insights_until' => $until, 'insights_window_limited' => $insightsWindowLimited, 'insights_unavailable' => $unavailableInsights];
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
            if ($optional) return ['_analytics_error' => true];
            throw new RuntimeException(data_get($response->json(), 'error.message', 'Meta analytics request failed (HTTP '.$response->status().').'));
        }
        return $response->json() ?? [];
    }

    private function normalizeInsights(array $metrics, string $until): array
    {
        return array_map(function (array $metric) use ($until): array {
            if (! isset($metric['values']) && isset($metric['total_value']['value'])) {
                $metric['values'] = [['value' => $metric['total_value']['value'], 'end_time' => $until]];
            } elseif (! isset($metric['values']) && array_key_exists('value', $metric)) {
                $metric['values'] = [['value' => $metric['value'], 'end_time' => $until]];
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
            $windows[] = [$cursor->toDateString(), $windowEnd->toDateString()];
            $cursor = $windowEnd->copy()->addDay();
        }
        return $windows;
    }

    private function metricValue(array $metric): mixed
    {
        return data_get($metric, 'total_value.value', data_get($metric, 'values.0.value', $metric['value'] ?? null));
    }
}
