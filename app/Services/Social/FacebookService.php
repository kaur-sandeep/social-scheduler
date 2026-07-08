<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class FacebookService
{
    private string $baseUrl;

    public function __construct(private readonly SocialAccountRepository $accounts)
    {
        $this->baseUrl = 'https://graph.facebook.com/'.config('facebook.graph_version');
    }

    public function authorizationUrl(): string
    {
        $state = Str::random(40);
        session(['facebook_oauth_state' => $state]);

        return 'https://www.facebook.com/'.config('facebook.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('facebook.app_id'),
            'redirect_uri' => config('facebook.redirect_uri') ?: URL::route('facebook.callback'),
            'scope' => implode(',', config('facebook.scopes')),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    public function handleCallback(int $userId, string $code): SocialAccount
    {
        $shortToken = $this->exchangeCode($code);
        $longToken = $this->exchangeForLongLivedToken($shortToken['access_token']);
        $profile = $this->get('/me', [
            'fields' => 'id,name,email',
            'access_token' => $longToken['access_token'],
        ]);

        $account = $this->accounts->upsertAccount([
            'user_id' => $userId,
            'provider' => 'facebook',
            'provider_user_id' => $profile['id'],
        ], [
            'provider_username' => $profile['name'] ?? null,
            'name' => $profile['name'] ?? null,
            'email' => $profile['email'] ?? null,
            'user_access_token' => $longToken['access_token'],
            'token_expires_at' => isset($longToken['expires_in']) ? now()->addSeconds((int) $longToken['expires_in']) : null,
            'status' => 'active',
            'connected_at' => now(),
            'disconnected_at' => null,
        ]);

        $this->syncPages($account);

        return $account->refresh();
    }

    public function syncPages(SocialAccount $account): void
    {
        $pages = $this->get('/me/accounts', [
            'fields' => 'id,name,category,access_token,tasks,picture{url}',
            'access_token' => $account->user_access_token,
        ]);

        foreach ($pages['data'] ?? [] as $page) {
            $this->accounts->upsertPage([
                'social_account_id' => $account->id,
                'provider' => 'facebook',
                'page_id' => $page['id'],
            ], [
                'page_name' => $page['name'],
                'category' => $page['category'] ?? null,
                'profile_image' => data_get($page, 'picture.data.url'),
                'page_access_token' => $page['access_token'],
                'permissions' => $page['tasks'] ?? [],
                'status' => 'active',
            ]);
        }
    }

    public function publish(Post $post): array
    {
        $page = $post->socialPage;

        if (! $page) {
            throw new RuntimeException('A Facebook post requires an active page.');
        }

        $media = $post->media;
        $firstMedia = $media->first();
        $endpoint = match ($firstMedia?->media_type) {
            'image' => "/{$page->page_id}/photos",
            'video' => "/{$page->page_id}/videos",
            default => "/{$page->page_id}/feed",
        };
        $payload = [
            'message' => $post->message,
            'access_token' => $page->page_access_token,
        ];

        if ($firstMedia?->media_type === 'video') {
            $payload['description'] = $post->message;
            unset($payload['message']);
        }

        if ($firstMedia) {
            $payload['published'] = true;
        }

        $start = microtime(true);
        $response = $firstMedia
            ? $this->postMedia($endpoint, $payload, $firstMedia->path)
            : $this->http()->asForm()->post($this->baseUrl.$endpoint, $payload);
        $elapsed = (int) ((microtime(true) - $start) * 1000);
        $body = $response->json() ?? [];

        $post->logs()->create([
            'platform' => 'facebook',
            'endpoint' => $endpoint,
            'api_request' => [
                'message' => $post->message,
                'media_count' => $media->count(),
                'upload_method' => $firstMedia ? 'multipart_source' : 'form',
            ],
            'api_response' => $body,
            'status_code' => $response->status(),
            'execution_time_ms' => $elapsed,
            'success' => $response->successful(),
            'failure_reason' => $response->successful() ? null : json_encode($body),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(data_get($body, 'error.message', 'Facebook publish failed.'));
        }

        return $body;
    }

    private function postMedia(string $endpoint, array $payload, string $storagePath)
    {
        $absolutePath = Storage::disk('public')->path($storagePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Media file not found: {$storagePath}");
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Media file is not readable: {$storagePath}");
        }

        try {
            return $this->http()
                ->attach('source', $handle, basename($absolutePath))
                ->post($this->baseUrl.$endpoint, $payload);
        } finally {
            fclose($handle);
        }
    }

    private function exchangeCode(string $code): array
    {
        return $this->get('/oauth/access_token', [
            'client_id' => config('facebook.app_id'),
            'redirect_uri' => config('facebook.redirect_uri') ?: URL::route('facebook.callback'),
            'client_secret' => config('facebook.app_secret'),
            'code' => $code,
        ]);
    }

    private function exchangeForLongLivedToken(string $token): array
    {
        return $this->get('/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('facebook.app_id'),
            'client_secret' => config('facebook.app_secret'),
            'fb_exchange_token' => $token,
        ]);
    }

    private function get(string $path, array $query): array
    {
        $response = $this->http()->get($this->baseUrl.$path, $query);

        if ($response->failed()) {
            throw new RuntimeException(data_get($response->json(), 'error.message', 'Facebook API request failed.'));
        }

        return $response->json() ?? [];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(30)->retry(2, 300);
    }
}
