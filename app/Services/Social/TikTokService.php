<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use App\Services\Social\Clients\TikTokClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class TikTokService
{
    use InteractsWithPosts;
    public function __construct(private readonly TikTokClient $client, private readonly SocialAccountRepository $accounts) {}

    public function authorizationUrl(): string
    {
        $this->assertConfigured();
        $state = Str::random(40);
        session(['tiktok_oauth_state' => $state]);

        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => config('tiktok.client_key'), 'response_type' => 'code',
            'scope' => implode(',', config('tiktok.scopes')), 'redirect_uri' => $this->redirectUri(), 'state' => $state,
        ]);
    }

    public function connect(int $userId, string $code): SocialAccount
    {
        $this->assertConfigured();
        $token = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri()]);
        $profile = $this->profile($token['access_token']);
        $identity = (string) ($token['open_id'] ?? data_get($profile, 'data.open_id') ?? '');
        if ($identity === '') throw new RuntimeException('TikTok did not return an account identity.');

        $account = $this->accounts->upsertAccount(['user_id' => $userId, 'provider' => 'tiktok', 'provider_user_id' => $identity], [
            'provider_username' => data_get($profile, 'data.display_name'), 'name' => data_get($profile, 'data.display_name') ?: $identity,
            'user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'status' => 'active', 'connected_at' => now(), 'disconnected_at' => null,
        ]);
        $this->accounts->upsertPage(['social_account_id' => $account->id, 'provider' => 'tiktok', 'page_id' => $identity], [
            'page_name' => data_get($profile, 'data.display_name') ?: 'TikTok account', 'category' => 'Creator account',
            'profile_image' => data_get($profile, 'data.avatar_url'), 'page_access_token' => $token['access_token'],
            'permissions' => explode(',', (string) ($token['scope'] ?? implode(',', config('tiktok.scopes')))), 'status' => 'active',
        ]);

        return $account->refresh();
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'tiktok');
        $video = $post->media->firstWhere('media_type', 'video');
        if (! $video) throw new RuntimeException('TikTok publishing requires one video.');
        $token = $this->accessToken($page->account);
        $creator = $this->client->creatorInfo($post, $token)['data'] ?? [];
        $privacy = config('tiktok.default_privacy');
        if (! in_array($privacy, $creator['privacy_level_options'] ?? [], true)) throw new RuntimeException('TikTok does not permit the configured privacy level for this creator. Set TIKTOK_DEFAULT_PRIVACY to one of the creator\'s available options.');

        return $this->client->publish($post, $token, ['post_info' => ['title' => $post->message, 'privacy_level' => $privacy, 'disable_duet' => (bool) ($creator['duet_disabled'] ?? false), 'disable_comment' => (bool) ($creator['comment_disabled'] ?? false), 'disable_stitch' => (bool) ($creator['stitch_disabled'] ?? false)], 'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $this->mediaUrl($video->path)]]);
    }

    public function status(Post $post): array { return $this->client->status($post, $this->accessToken($this->page($post, 'tiktok')->account), $post->provider_post_id); }

    private function accessToken(SocialAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isFuture()) return $account->user_access_token;
        if (! $account->refresh_token) throw new RuntimeException('Reconnect TikTok: the access token has expired.');
        $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        $account->update(['user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? $account->refresh_token, 'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0))]);
        $account->pages()->update(['page_access_token' => $token['access_token']]);

        return $token['access_token'];
    }

    private function token(array $payload): array
    {
        $response = Http::asForm()->timeout(30)->post(config('tiktok.api_url').'/v2/oauth/token/', array_merge($payload, ['client_key' => config('tiktok.client_key'), 'client_secret' => config('tiktok.client_secret')]));
        if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'error_description', 'TikTok authorization failed.'));

        return $response->json() ?? [];
    }

    private function profile(string $token): array
    {
        $response = Http::acceptJson()->withToken($token)->timeout(30)->get(config('tiktok.api_url').'/v2/user/info/', ['fields' => 'open_id,display_name,avatar_url']);
        if ($response->failed()) throw new RuntimeException('Could not read TikTok account information.');

        return $response->json() ?? [];
    }

    private function redirectUri(): string { return config('tiktok.redirect_uri') ?: URL::route('tiktok.callback'); }
    private function assertConfigured(): void { if (! config('tiktok.client_key') || ! config('tiktok.client_secret')) throw new RuntimeException('Set TIKTOK_CLIENT_KEY and TIKTOK_CLIENT_SECRET before connecting TikTok.'); }
}
