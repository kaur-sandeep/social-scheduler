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

    public function dashboardForDailyReport(SocialPage $page, string $since, string $until): array
    {
        $account = $page->account;
        if (! $account || $page->provider !== 'instagram' || $account->status !== 'active' || $page->status !== 'active') {
            throw new RuntimeException('This Instagram account is disconnected.');
        }

        $token = $this->freshToken($page);
        $id = $page->page_id;
        $profile = $this->request('/'.$id, [
            'fields' => 'user_id,username,account_type,media_count,followers_count,profile_picture_url',
            'access_token' => $token,
        ]);
        $insights = [];
        $unavailable = [];
        foreach ([['views', 'day'], ['reach', 'day'], ['follower_count', 'lifetime']] as [$metric, $period]) {
            $params = ['metric' => $metric, 'period' => $period, 'access_token' => $token];
            if ($metric !== 'follower_count') {
                $params['since'] = Carbon::parse($since)->startOfDay()->timestamp;
                $params['until'] = Carbon::parse($until)->endOfDay()->timestamp;
            }
            try {
                $result = $this->request('/'.$id.'/insights', $params);
                foreach ($result['data'] ?? [] as $row) $insights[] = $this->normalizeMetric($row, $until);
            } catch (RuntimeException $exception) {
                $unavailable[] = $metric;
                Log::notice('Instagram Login insight is unavailable for the connected account.', ['social_page_id' => $page->id, 'metric' => $metric, 'message' => $exception->getMessage()]);
            }
        }

        $media = $this->paged('/'.$id.'/media', [
            'fields' => 'id,caption,media_type,media_product_type,timestamp,permalink,like_count,comments_count',
            'limit' => 100,
            'access_token' => $token,
        ]);
        $mediaRows = [];
        foreach ($media as $item) {
            if (empty($item['timestamp'])) continue;
            $date = Carbon::parse($item['timestamp'])->toDateString();
            if ($date < $since || $date > $until) continue;
            $metrics = ['likes' => $item['like_count'] ?? null, 'comments' => $item['comments_count'] ?? null];
            try {
                $postInsights = $this->request('/'.$item['id'].'/insights', ['metric' => 'views,reach,saved,shares', 'metric_type' => 'total_value', 'access_token' => $token]);
                foreach ($postInsights['data'] ?? [] as $metric) $metrics[$metric['name']] = data_get($metric, 'total_value.value', data_get($metric, 'values.0.value'));
            } catch (RuntimeException $exception) {
                Log::notice('Instagram media insights are unavailable.', ['social_page_id' => $page->id, 'media_id' => $item['id'] ?? null, 'message' => $exception->getMessage()]);
            }
            $mediaRows[] = ['id' => $item['id'], 'published_at' => $item['timestamp'], 'metrics' => $metrics];
        }

        $page->update(['last_synced_at' => now(), 'page_name' => $profile['username'] ?? $page->page_name, 'profile_image' => $profile['profile_picture_url'] ?? $page->profile_image]);
        return ['account' => ['followers_count' => $profile['followers_count'] ?? null, 'media_count' => $profile['media_count'] ?? null], 'insights' => $insights, 'media' => $mediaRows, 'insights_unavailable' => $unavailable];
    }

    private function freshToken(SocialPage $page): string
    {
        $account = $page->account;
        $token = $page->page_access_token ?: $account?->user_access_token;
        if (! $token) throw new RuntimeException('Instagram access token is missing. Reconnect this account.');
        if ($account?->token_expires_at?->gt(now()->addDays(7))) return $token;

        $response = Http::timeout(25)->get($this->base.'/refresh_access_token', ['grant_type' => 'ig_refresh_token', 'access_token' => $token]);
        if ($response->failed() || ! $response->json('access_token')) {
            $account->update(['status' => 'reauthorization_required']);
            throw new RuntimeException('Instagram access token expired or could not be refreshed. Reconnect this account.');
        }
        $token = $response->json('access_token');
        $expires = now()->addSeconds((int) $response->json('expires_in', 5184000));
        $account->update(['user_access_token' => $token, 'token_expires_at' => $expires, 'status' => 'active']);
        $page->update(['page_access_token' => $token, 'last_synced_at' => now()]);
        return $token;
    }

    private function normalizeMetric(array $metric, string $until): array
    {
        if (isset($metric['total_value'])) {
            $metric['values'] = [['value' => data_get($metric, 'total_value.value'), 'end_time' => $until]];
            $metric['_analytics_total_only'] = true;
        }
        return $metric;
    }

    private function paged(string $path, array $query): array
    {
        $items = [];
        $url = $this->base.$path;
        do {
            $response = Http::timeout(25)->get($url, $query);
            if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'error.message', 'Instagram media request failed.'));
            $json = $response->json() ?? [];
            $items = array_merge($items, $json['data'] ?? []);
            $url = data_get($json, 'paging.next');
            $query = [];
        } while ($url && count($items) < 1000);
        return $items;
    }

    private function request(string $path, array $query): array
    {
        $response = Http::timeout(25)->get($this->base.$path, $query);
        if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'error.message', 'Instagram API request failed (HTTP '.$response->status().').'));
        return $response->json() ?? [];
    }
}
