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
            'page_name' => $profile['name'] ?? 'LinkedIn profile', 'category' => 'LinkedIn Personal Profile', 'profile_image' => $profile['picture'] ?? null,
            'page_access_token' => $token['access_token'], 'permissions' => config('linkedin.scopes'), 'status' => 'active',
        ]);

        return $account->refresh();
    }

    /**
     * Sync the LinkedIn Company Pages where the authenticated member can post.
     * Personal profile sync deliberately remains separate, so a missing Company
     * Page permission never removes or changes personal-profile publishing.
     */
    public function syncCompanyPages(SocialAccount $account): int
    {
        $token = $this->accessToken($account);
        $account->pages()->where('provider', 'linkedin')->where('page_id', 'like', 'urn:li:organization:%')->update(['status' => 'disconnected']);

        $synced = 0;
        $start = 0;

        do {
            $response = $this->linkedinGet('/rest/organizationAcls', $token, [
                'q' => 'roleAssignee', 'state' => 'APPROVED', 'count' => 100, 'start' => $start,
            ]);
            $elements = $response['elements'] ?? [];
            $organizations = collect($elements)
                ->filter(fn (array $authorization) => in_array($authorization['role'] ?? null, ['ADMINISTRATOR', 'CONTENT_ADMINISTRATOR', 'DIRECT_SPONSORED_CONTENT_POSTER'], true))
                ->pluck('organization')
                ->filter(fn ($urn) => is_string($urn) && str_starts_with($urn, 'urn:li:organization:'))
                ->unique()
                ->values();

            foreach ($organizations as $organizationUrn) {
                $organizationId = str($organizationUrn)->afterLast(':')->toString();
                try {
                    $organization = $this->linkedinGet('/rest/organizations/'.$organizationId, $token);
                } catch (RuntimeException) {
                    // The authorization result is enough to post. Keep the Company
                    // Page selectable even if LinkedIn does not expose its metadata.
                    $organization = [];
                }
                $this->accounts->upsertPage([
                    'social_account_id' => $account->id,
                    'provider' => 'linkedin',
                    'page_id' => $organizationUrn,
                ], [
                    'page_name' => $organization['localizedName'] ?? $organization['name'] ?? "LinkedIn Company Page {$organizationId}",
                    'category' => 'LinkedIn Company Page',
                    'profile_image' => data_get($organization, 'logoV2.original~.elements.0.identifiers.0.identifier'),
                    'page_access_token' => $token,
                    'permissions' => config('linkedin.scopes'),
                    'status' => 'active',
                ]);
                $synced++;
            }

            $start += count($elements);
        } while (count($elements) === 100);

        return $synced;
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'linkedin');
        $token = $this->accessToken($page->account);
        $payload = ['author' => $page->page_id, 'commentary' => $post->message, 'visibility' => 'PUBLIC', 'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []], 'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false];

        if ($post->media->isNotEmpty()) {
            if ($post->media->count() !== 1 || $post->media->first()->media_type !== 'image') throw new RuntimeException('LinkedIn currently supports one image attachment per post. Video and multi-image uploads are not supported.');
            $media = $post->media->first();
            if (! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/gif'], true)) throw new RuntimeException('LinkedIn images must be JPEG, PNG, or GIF files.');
            $payload['content'] = ['media' => ['id' => $this->client->uploadImage($post, $token, $page->page_id, $media->path, $media->mime_type)]];
        }

        return $this->client->createPost($post, $token, $payload);
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
    private function linkedinGet(string $path, string $token, array $query = []): array
    {
        $response = Http::acceptJson()->withToken($token)->withHeaders([
            'Linkedin-Version' => config('linkedin.version'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->timeout(30)->get(config('linkedin.api_url').$path, $query);

        if ($response->failed()) {
            throw new RuntimeException(data_get($response->json(), 'message', 'LinkedIn Company Pages could not be read.'));
        }

        return $response->json() ?? [];
    }
    private function redirectUri(): string { return config('linkedin.redirect_uri') ?: URL::route('linkedin.callback'); }
    private function assertConfigured(): void { if (! config('linkedin.client_id') || ! config('linkedin.client_secret')) throw new RuntimeException('Set LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET before connecting LinkedIn.'); }
}
