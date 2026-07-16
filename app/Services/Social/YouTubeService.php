<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use App\Services\Social\Clients\GoogleClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class YouTubeService
{
    use InteractsWithPosts;

    public function __construct(private readonly GoogleClient $client, private readonly SocialAccountRepository $accounts) {}

    public function authorizationUrl(): string
    {
        $state = Str::random(40);
        session(['youtube_oauth_state' => $state]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', config('google.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connect(int $userId, string $code): SocialAccount
    {
        $token = $this->client->exchangeCode($code, $this->redirectUri());
        $channel = $this->client->channel($token['access_token']);
        $account = $this->accounts->upsertAccount(['user_id' => $userId, 'provider' => 'youtube', 'provider_user_id' => $channel['id']], [
            'provider_username' => data_get($channel, 'snippet.customUrl'),
            'name' => data_get($channel, 'snippet.title'),
            'user_access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'status' => 'active', 'connected_at' => now(), 'disconnected_at' => null,
        ]);
        $this->accounts->upsertPage(['social_account_id' => $account->id, 'provider' => 'youtube', 'page_id' => $channel['id']], [
            'page_name' => data_get($channel, 'snippet.title', 'YouTube channel'),
            'profile_image' => data_get($channel, 'snippet.thumbnails.default.url'),
            'page_access_token' => $token['access_token'],
            'permissions' => config('google.scopes'), 'status' => 'active',
        ]);
        return $account->refresh();
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'youtube');
        $video = $post->media->firstWhere('media_type', 'video');
        if (! $video) throw new RuntimeException('YouTube publishing requires one video.');
        $token = $this->accessToken($page->account);
        return $this->client->uploadVideo($post, $token, $video->path, ['snippet' => ['title' => Str::limit($post->message, 100, ''), 'description' => $post->message, 'categoryId' => '22'], 'status' => ['privacyStatus' => config('google.youtube_privacy'), 'selfDeclaredMadeForKids' => false]]);
    }

    private function accessToken(SocialAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isFuture()) return $account->user_access_token;
        if (! $account->refresh_token) throw new RuntimeException('Reconnect YouTube: the account has no refresh token.');
        $token = $this->client->refreshAccessToken($account->refresh_token);
        $account->update(['user_access_token' => $token['access_token'], 'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)), 'status' => 'active']);
        $account->pages()->update(['page_access_token' => $token['access_token']]);
        return $token['access_token'];
    }

    private function redirectUri(): string { return config('google.redirect_uri') ?: URL::route('youtube.callback'); }
}
