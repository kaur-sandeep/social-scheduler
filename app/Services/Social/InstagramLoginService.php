<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Repositories\SocialAccountRepository;
use App\Services\ProjectCredentialService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class InstagramLoginService
{
    private string $base;

    public function __construct(private readonly SocialAccountRepository $accounts, private readonly ProjectCredentialService $credentials)
    {
        $this->base = 'https://graph.instagram.com/'.config('facebook.graph_version', 'v23.0');
    }

    public function authorizationUrl(): string
    {
        $credential = $this->credentials->fromSession('instagram');
        $state = Str::random(48);
        session(['instagram_oauth_state' => $state]);

        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => $credential->client_id,
            'redirect_uri' => $credential->redirect_uri ?: URL::route('instagram.callback'),
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_manage_insights',
            'state' => $state,
            'enable_fb_login' => '0',
            'force_authentication' => '1',
        ]);
    }

    public function handleCallback(int $userId, string $code): SocialAccount
    {
        $credential = $this->credentials->fromSession('instagram');
        $redirectUri = $credential->redirect_uri ?: URL::route('instagram.callback');
        $short = Http::asForm()->timeout(30)->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $credential->client_id,
            'client_secret' => $credential->client_secret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        if ($short->failed() || ! $short->json('access_token')) {
            throw new RuntimeException(data_get($short->json(), 'error_message', data_get($short->json(), 'error.message', 'Instagram authorization code exchange failed.')));
        }

        $long = Http::timeout(30)->get($this->base.'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $credential->client_secret,
            'access_token' => $short->json('access_token'),
        ]);
        if ($long->failed() || ! $long->json('access_token')) {
            throw new RuntimeException(data_get($long->json(), 'error.message', 'Instagram long-lived token exchange failed.'));
        }

        $token = $long->json('access_token');
        $profile = $this->request('/me', [
            'fields' => 'user_id,username,account_type,media_count,followers_count,profile_picture_url',
            'access_token' => $token,
        ]);
        $instagramId = (string) ($profile['user_id'] ?? $profile['id'] ?? '');
        if ($instagramId === '') throw new RuntimeException('Instagram did not return an account ID.');
        $projectId = (int) session('oauth_project_id');
        $expiresAt = isset($long['expires_in']) ? now()->addSeconds((int) $long['expires_in']) : now()->addDays(60);
        $username = $profile['username'] ?? 'Instagram account';

        $account = $this->accounts->upsertAccount([
            'project_id' => $projectId,
            'user_id' => $userId,
            'provider' => 'instagram',
            'provider_user_id' => $instagramId,
        ], [
            'authentication_method' => 'instagram_login',
            'provider_username' => $username,
            'name' => '@'.$username,
            'user_access_token' => $token,
            'refresh_token' => null,
            'token_expires_at' => $expiresAt,
            'status' => 'active',
            'connected_at' => now(),
            'disconnected_at' => null,
        ]);

        $this->accounts->upsertPage([
            'social_account_id' => $account->id,
            'provider' => 'instagram',
            'page_id' => $instagramId,
        ], [
            'page_name' => $username,
            'category' => $profile['account_type'] ?? 'Professional account',
            'profile_image' => $profile['profile_picture_url'] ?? null,
            'page_access_token' => $token,
            'permissions' => ['instagram_business_basic', 'instagram_business_manage_insights'],
            'status' => 'active',
            'last_synced_at' => now(),
        ]);

        return $account->refresh();
    }

    public function dashboardForDailyReport(SocialPage $page, string $since, string $until, bool $includeMedia = true): array
    {
        $account = $page->account;
        if (! $account || $page->provider !== 'instagram' || ! in_array($account->status, ['active', 'reauthorization_required'], true) || $page->status !== 'active') {
            throw new RuntimeException('This Instagram account is disconnected.');
        }

        $token = $this->freshToken($page);
        $profileQuery = [
            'fields' => 'user_id,username,account_type,media_count,followers_count,profile_picture_url',
            'access_token' => $token,
        ];
        // Instagram Login tokens are scoped to this Instagram user. Use the
        // stored Instagram user ID consistently on profile, insights and media.
        $profile = $this->requestWithTokenRecovery($page, '/'.$page->page_id, $profileQuery, $token);
        if ($account->status === 'reauthorization_required') {
            $account->update(['status' => 'active']);
        }
        $insights = [];
        $unavailable = [];
        foreach ([['views', 'day', 'total_value'], ['reach', 'day', 'time_series'], ['follower_count', 'lifetime', 'total_value']] as [$metric, $period, $metricType]) {
            $params = ['metric' => $metric, 'period' => $period, 'metric_type' => $metricType, 'access_token' => $token];
            if ($metric !== 'follower_count') {
                $params['since'] = Carbon::parse($since)->startOfDay()->timestamp;
                $params['until'] = Carbon::parse($until)->endOfDay()->timestamp;
            }
            try {
                $result = $this->requestWithTokenRecovery($page, '/'.$page->page_id.'/insights', $params, $token);
                $found = false;
                foreach ($result['data'] ?? [] as $row) {
                    $value = data_get($row, 'total_value.value', data_get($row, 'values.0.value', $row['value'] ?? null));
                    if (($row['name'] ?? null) === $metric && is_numeric($value)) $found = true;
                    $insights[] = $this->normalizeMetric($row, $until, $since === $until);
                }
                if (! $found) $unavailable[] = $metric;
            } catch (RuntimeException $exception) {
                $unavailable[] = $metric;
                Log::notice('Instagram Login insight is unavailable for the connected account.', ['social_page_id' => $page->id, 'metric' => $metric, 'message' => $exception->getMessage()]);
            }
        }

        $mediaUnavailable = false;
        $media = [];
        if ($includeMedia) {
            try {
                // The media edge belongs to the authenticated IG user ID. Keep
                // /me for profile/insights, but use the explicit ID for media.
                $media = $this->pagedWithTokenRecovery($page, '/'.$page->page_id.'/media', [
                    'fields' => 'id,caption,media_type,media_product_type,timestamp,permalink,like_count,comments_count',
                    'limit' => 100,
                    'access_token' => $token,
                ], $token);
            } catch (RuntimeException $exception) {
                // Media details enrich the report; they must not block profile and
                // account-level insights when Meta rejects the media edge.
                $mediaUnavailable = true;
                Log::notice('Instagram post details are unavailable; returning account-level analytics.', [
                    'social_page_id' => $page->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
        $mediaRows = [];
        $mediaInsightsUnavailable = [];
        $timezone = config('social_reports.timezone', config('app.timezone', 'UTC'));
        foreach ($media as $item) {
            if (empty($item['timestamp'])) continue;
            $date = Carbon::parse($item['timestamp'])->setTimezone($timezone)->toDateString();
            if ($date < $since || $date > $until) continue;
            $metrics = ['likes' => $item['like_count'] ?? null, 'comments' => $item['comments_count'] ?? null];
            $mediaType = strtoupper((string) ($item['media_product_type'] ?? $item['media_type'] ?? ''));
            foreach (['like_count' => 'likes', 'comments_count' => 'comments'] as $field => $name) {
                if (! array_key_exists($field, $item) || $item[$field] === null) $mediaInsightsUnavailable[] = ['media_id' => $item['id'], 'metric' => $name, 'reason' => 'not_returned'];
            }
            // Meta availability varies by media type. Do not ask for shares on
            // still images; query each eligible metric separately so one
            // unsupported metric cannot hide the other returned values.
            $supported = ['views', 'reach', 'saved'];
            if (in_array($mediaType, ['REEL', 'VIDEO'], true) || strtoupper((string) ($item['media_type'] ?? '')) === 'VIDEO') $supported[] = 'shares';
            foreach ($supported as $metricName) {
                try {
                    $postInsights = $this->requestWithTokenRecovery($page, '/'.$item['id'].'/insights', ['metric' => $metricName, 'metric_type' => 'total_value', 'access_token' => $token], $token);
                    $found = false;
                    foreach ($postInsights['data'] ?? [] as $metric) {
                        if (($metric['name'] ?? null) === $metricName) {
                            $value = data_get($metric, 'total_value.value', data_get($metric, 'values.0.value'));
                            $metrics[$metricName] = is_numeric($value) ? $value : null;
                            $found = is_numeric($value);
                        }
                    }
                    if (! $found) $mediaInsightsUnavailable[] = ['media_id' => $item['id'], 'metric' => $metricName, 'reason' => 'not_returned'];
                } catch (RuntimeException $exception) {
                    $mediaInsightsUnavailable[] = ['media_id' => $item['id'], 'metric' => $metricName, 'reason' => 'request_failed'];
                    Log::notice('Instagram media insight request failed.', ['social_page_id' => $page->id, 'media_id' => $item['id'] ?? null, 'metric' => $metricName, 'message' => $exception->getMessage()]);
                }
            }
            $mediaRows[] = [
                'id' => $item['id'],
                'text' => $item['caption'] ?? '',
                'type' => $item['media_type'] ?? $item['media_product_type'] ?? 'Other',
                'product_type' => $item['media_product_type'] ?? null,
                'url' => $item['permalink'] ?? null,
                'thumbnail' => $item['thumbnail_url'] ?? $item['media_url'] ?? null,
                'published_at' => $item['timestamp'],
                'metrics' => $metrics,
            ];
        }

        $page->update(['last_synced_at' => now(), 'page_name' => $profile['username'] ?? $page->page_name, 'profile_image' => $profile['profile_picture_url'] ?? $page->profile_image]);
        return ['account' => ['followers_count' => $profile['followers_count'] ?? null, 'media_count' => $profile['media_count'] ?? null], 'insights' => $insights, 'media' => $mediaRows, 'media_unavailable' => $mediaUnavailable, 'media_insights_unavailable' => $mediaInsightsUnavailable, 'insights_unavailable' => $unavailable];
    }

    public function dashboard(SocialPage $page, string $since, string $until): array
    {
        return $this->dashboardForDailyReport($page, $since, $until);
    }

    private function freshToken(SocialPage $page): string
    {
        $account = $page->account;
        $token = $page->page_access_token ?: $account?->user_access_token;
        if (! $token) throw new RuntimeException('Instagram access token is missing. Reconnect this account.');
        if ($account?->token_expires_at?->gt(now()->addDays(7))) return $token;

        return $this->refreshToken($page);
    }

    private function refreshToken(SocialPage $page): string
    {
        $account = $page->account;
        $token = $page->page_access_token ?: $account?->user_access_token;
        if (! $token || ! $account) throw new RuntimeException('Instagram access token is missing. Reconnect this account.');

        // Instagram's long-lived token refresh endpoint is not versioned.
        $response = Http::timeout(25)->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]);
        if ($response->failed() || ! $response->json('access_token')) {
            $account->update(['status' => 'reauthorization_required']);
            Log::warning('Instagram access token refresh failed.', [
                'social_page_id' => $page->id,
                'http_status' => $response->status(),
                'meta_error' => data_get($response->json(), 'error.message'),
                'meta_error_code' => data_get($response->json(), 'error.code'),
            ]);
            throw new RuntimeException('Instagram rejected this access token. Reconnect the Instagram account and retry.');
        }
        $token = $response->json('access_token');
        $expires = now()->addSeconds((int) $response->json('expires_in', 5184000));
        $account->update(['user_access_token' => $token, 'token_expires_at' => $expires, 'status' => 'active']);
        $page->update(['page_access_token' => $token, 'last_synced_at' => now()]);
        return $token;
    }

    private function isInvalidTokenError(RuntimeException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'invalid oauth')
            || str_contains($message, 'invalid access token')
            || str_contains($message, 'cannot parse access token');
    }

    private function requestWithTokenRecovery(SocialPage $page, string $path, array $query, string &$token): array
    {
        try {
            return $this->request($path, $query);
        } catch (RuntimeException $exception) {
            if (! $this->isInvalidTokenError($exception)) throw $exception;
            Log::warning('Instagram rejected an API token; attempting a refresh and one retry.', ['social_page_id' => $page->id, 'api_path' => $path]);
            $token = $this->refreshToken($page);
            $query['access_token'] = $token;
            try {
                return $this->request($path, $query);
            } catch (RuntimeException $retryException) {
                // A valid refreshed token can still fail for an unrelated reason
                // (for example, a permission or unsupported-field error). Only
                // mark the account for reauthorization if Meta rejects the token.
                if (! $this->isInvalidTokenError($retryException)) throw $retryException;

                $page->account?->update(['status' => 'reauthorization_required']);
                throw new RuntimeException('Instagram still rejects the refreshed token. Reconnect the Instagram account.', previous: $retryException);
            }
        }
    }

    private function pagedWithTokenRecovery(SocialPage $page, string $path, array $query, string &$token): array
    {
        try {
            return $this->paged($path, $query);
        } catch (RuntimeException $exception) {
            if (! $this->isInvalidTokenError($exception)) throw $exception;
            Log::warning('Instagram rejected a media API token; attempting a refresh and one retry.', ['social_page_id' => $page->id, 'api_path' => $path]);
            $token = $this->refreshToken($page);
            $query['access_token'] = $token;
            try {
                return $this->paged($path, $query);
            } catch (RuntimeException $retryException) {
                if (! $this->isInvalidTokenError($retryException)) throw $retryException;

                throw new RuntimeException('Instagram still rejects the refreshed token for the media endpoint.', previous: $retryException);
            }
        }
    }

    private function normalizeMetric(array $metric, string $until, bool $singleDay = false): array
    {
        if (isset($metric['total_value'])) {
            $metric['values'] = [['value' => data_get($metric, 'total_value.value'), 'end_time' => $until]];
            $metric['_analytics_total_only'] = ! $singleDay;
        }
        return $metric;
    }

    private function paged(string $path, array $query): array
    {
        $items = [];
        $seen = [];
        $url = $this->base.$path;
        do {
            $response = Http::timeout(25)->get($url, $query);
            if ($response->failed()) {
                $error = $response->json('error') ?? [];
                Log::warning('Instagram Graph API media request failed.', [
                    'http_status' => $response->status(),
                    'meta_error_code' => $error['code'] ?? null,
                    'meta_error_subcode' => $error['error_subcode'] ?? null,
                    'meta_error_type' => $error['type'] ?? null,
                    'meta_error_message' => $error['message'] ?? null,
                    'fbtrace_id' => $error['fbtrace_id'] ?? null,
                ]);
                throw new RuntimeException(data_get($response->json(), 'error.message', 'Instagram media request failed (HTTP '.$response->status().').'));
            }
            $json = $response->json() ?? [];
            $loggedResponse = $json;
            if (isset($loggedResponse['paging']['next'])) $loggedResponse['paging']['next'] = '[redacted pagination URL]';
            Log::info('Instagram media API page retrieved.', ['api_path' => $path, 'http_status' => $response->status(), 'returned_count' => count($json['data'] ?? []), 'has_next_page' => (bool) data_get($json, 'paging.next'), 'meta_response' => $loggedResponse]);
            $items = array_merge($items, $json['data'] ?? []);
            $url = data_get($json, 'paging.next');
            if ($url) {
                if (isset($seen[$url])) throw new RuntimeException('Instagram media pagination repeated a next URL.');
                $parsed = parse_url($url);
                if (($parsed['scheme'] ?? '') !== 'https' || strtolower($parsed['host'] ?? '') !== 'graph.instagram.com') {
                    throw new RuntimeException('Instagram media pagination returned an unexpected host.');
                }
                $seen[$url] = true;
            }
            $query = [];
        } while ($url);
        $deduplicated = [];
        foreach ($items as $item) if (! empty($item['id'])) $deduplicated[(string) $item['id']] = $item;
        return array_values($deduplicated);
    }

    private function request(string $path, array $query): array
    {
        $response = Http::timeout(25)->get($this->base.$path, $query);
        if ($response->failed()) {
            $error = $response->json('error') ?? [];
            Log::warning('Instagram Graph API request failed.', [
                'api_path' => $path,
                'http_status' => $response->status(),
                'meta_error_code' => $error['code'] ?? null,
                'meta_error_subcode' => $error['error_subcode'] ?? null,
                'meta_error_type' => $error['type'] ?? null,
                'meta_error_message' => $error['message'] ?? null,
                'fbtrace_id' => $error['fbtrace_id'] ?? null,
            ]);
            throw new RuntimeException(data_get($response->json(), 'error.message', 'Instagram API request failed (HTTP '.$response->status().').'));
        }
        $json = $response->json() ?? [];
        Log::info('Instagram Graph API request succeeded.', ['api_path' => $path, 'http_status' => $response->status(), 'meta_response' => $json]);
        return $json;
    }
}
