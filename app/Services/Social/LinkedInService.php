<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use App\Services\Social\Clients\LinkedInClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class LinkedInService
{
    use InteractsWithPosts;
    public function __construct(private readonly LinkedInClient $client, private readonly SocialAccountRepository $accounts) {}

    public function authorizationUrl(): string
    {
        $this->assertConfigured();
        $state = Str::random(40);
        session(['linkedin_oauth_state' => $state]);

        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code', 'client_id' => config('linkedin.client_id'),
            'redirect_uri' => $this->redirectUri(), 'state' => $state,
            'scope' => implode(' ', config('linkedin.scopes')),
        ]);
    }

    public function connect(int $userId, string $code): SocialAccount
    {
        $this->assertConfigured();
        $token = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri()]);
        $profile = $this->get('/v2/userinfo', $token['access_token']);
        $memberId = (string) ($profile['sub'] ?? '');
        if ($memberId === '') throw new RuntimeException('LinkedIn did not return a member identity.');

        $account = $this->accounts->upsertAccount(['user_id' => $userId, 'provider' => 'linkedin', 'provider_user_id' => $memberId], [
            'provider_username' => $profile['name'] ?? null, 'name' => $profile['name'] ?? null, 'email' => $profile['email'] ?? null,
            'user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'status' => 'active', 'connected_at' => now(), 'disconnected_at' => null,
        ]);
        $this->accounts->upsertPage(['social_account_id' => $account->id, 'provider' => 'linkedin', 'page_id' => "urn:li:person:{$memberId}"], [
            'page_name' => $profile['name'] ?? 'LinkedIn profile', 'profile_image' => $profile['picture'] ?? null,
            'page_access_token' => $token['access_token'], 'permissions' => config('linkedin.scopes'), 'status' => 'active',
        ]);

        return $account->refresh();
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'linkedin');
        if ($post->media->isNotEmpty()) throw new RuntimeException('LinkedIn image and video uploads are not enabled yet; publish this LinkedIn post without attachments.');
        return $this->client->createPost($post, $this->accessToken($page->account), ['author' => $page->page_id, 'commentary' => $post->message, 'visibility' => 'PUBLIC', 'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []], 'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false]);
    }

    private function accessToken(SocialAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isFuture()) return $account->user_access_token;
        if (! $account->refresh_token) throw new RuntimeException('Reconnect LinkedIn: the access token has expired.');
        $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        $account->update(['user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? $account->refresh_token, 'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null]);
        $account->pages()->get()->each->update(['page_access_token' => $token['access_token']]);
        return $token['access_token'];
    }

    private function token(array $payload): array
    {
        $response = Http::asForm()->timeout(30)->post('https://www.linkedin.com/oauth/v2/accessToken', array_merge($payload, ['client_id' => config('linkedin.client_id'), 'client_secret' => config('linkedin.client_secret')]));
        if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'error_description', 'LinkedIn authorization failed.'));
        return $response->json();
    }

    private function get(string $path, string $token): array { $response = Http::acceptJson()->withToken($token)->timeout(30)->get(config('linkedin.api_url').$path); if ($response->failed()) throw new RuntimeException('Could not read the LinkedIn profile.'); return $response->json() ?? []; }
    private function redirectUri(): string { return config('linkedin.redirect_uri') ?: URL::route('linkedin.callback'); }
    private function assertConfigured(): void { if (! config('linkedin.client_id') || ! config('linkedin.client_secret')) throw new RuntimeException('Set LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET before connecting LinkedIn.'); }
}
