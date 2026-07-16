<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use App\Services\Social\Clients\PinterestClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class PinterestService
{
    use InteractsWithPosts;
    public function __construct(private readonly PinterestClient $client, private readonly SocialAccountRepository $accounts) {}

    public function authorizationUrl(): string
    {
        $this->assertConfigured(); $state = Str::random(40); session(['pinterest_oauth_state' => $state]);
        return 'https://www.pinterest.com/oauth/?'.http_build_query(['client_id' => config('pinterest.app_id'), 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code', 'scope' => implode(',', config('pinterest.scopes')), 'state' => $state]);
    }

    public function connect(int $userId, string $code): SocialAccount
    {
        $this->assertConfigured(); $token = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(), 'continuous_refresh' => 'true']);
        $profile = $this->get('/user_account', $token['access_token']); $identity = (string) ($profile['username'] ?? $profile['id'] ?? '');
        if ($identity === '') throw new RuntimeException('Pinterest did not return an account identity.');
        $account = $this->accounts->upsertAccount(['user_id' => $userId, 'provider' => 'pinterest', 'provider_user_id' => $identity], [
            'provider_username' => $profile['username'] ?? null, 'name' => $profile['username'] ?? $identity,
            'user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'status' => 'active', 'connected_at' => now(), 'disconnected_at' => null,
        ]);
        foreach ($this->get('/boards', $token['access_token'])['items'] ?? [] as $board) $this->accounts->upsertPage(['social_account_id' => $account->id, 'provider' => 'pinterest', 'page_id' => $board['id']], ['page_name' => $board['name'], 'category' => $board['privacy'] ?? 'Board', 'profile_image' => data_get($board, 'media.image_cover_url'), 'page_access_token' => $token['access_token'], 'permissions' => config('pinterest.scopes'), 'status' => 'active']);
        return $account->refresh();
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'pinterest'); $media = $post->media->first();
        if (! $media || $media->media_type !== 'image') throw new RuntimeException('Pinterest currently requires one image attachment.');
        return $this->client->createPin($post, $this->accessToken($page->account), $page->page_id, ['title' => str($post->message)->limit(100)->toString(), 'description' => $post->message, 'media_source' => ['source_type' => 'image_url', 'url' => $this->mediaUrl($media->path)]]);
    }

    private function accessToken(SocialAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isFuture()) return $account->user_access_token;
        if (! $account->refresh_token) throw new RuntimeException('Reconnect Pinterest: the access token has expired.');
        $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token, 'continuous_refresh' => 'true']);
        $account->update(['user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? $account->refresh_token, 'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null]); $account->pages()->get()->each->update(['page_access_token' => $token['access_token']]); return $token['access_token'];
    }

    private function token(array $payload): array { $response = Http::asForm()->withBasicAuth(config('pinterest.app_id'), config('pinterest.app_secret'))->timeout(30)->post(config('pinterest.api_url').'/oauth/token', $payload); if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'message', 'Pinterest authorization failed.')); return $response->json(); }
    private function get(string $path, string $token): array { $response = Http::acceptJson()->withToken($token)->timeout(30)->get(config('pinterest.api_url').$path, $path === '/boards' ? ['page_size' => 100] : []); if ($response->failed()) throw new RuntimeException('Could not read Pinterest account information.'); return $response->json() ?? []; }
    private function redirectUri(): string { return config('pinterest.redirect_uri') ?: URL::route('pinterest.callback'); }
    private function assertConfigured(): void { if (! config('pinterest.app_id') || ! config('pinterest.app_secret')) throw new RuntimeException('Set PINTEREST_APP_ID and PINTEREST_APP_SECRET before connecting Pinterest.'); }
}
