<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use App\Services\Social\Clients\TwitterClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class TwitterService
{
    use InteractsWithPosts;
    public function __construct(private readonly TwitterClient $client, private readonly SocialAccountRepository $accounts) {}

    public function authorizationUrl(): string
    {
        $this->assertConfigured();
        $state = Str::random(40);
        $verifier = Str::random(96);
        session(['twitter_oauth_state' => $state, 'twitter_oauth_verifier' => $verifier]);

        return 'https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => config('twitter.client_id'), 'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', config('twitter.scopes')), 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
        ]);
    }

    public function connect(int $userId, string $code, string $verifier): SocialAccount
    {
        $this->assertConfigured();
        $token = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(), 'code_verifier' => $verifier]);
        $response = Http::acceptJson()->withToken($token['access_token'])->timeout(30)->get(config('twitter.api_url').'/2/users/me', ['user.fields' => 'profile_image_url']);
        if ($response->failed() || ! ($profile = data_get($response->json(), 'data'))) throw new RuntimeException('Could not read the X account profile.');
        $account = $this->accounts->upsertAccount(['user_id' => $userId, 'provider' => 'twitter', 'provider_user_id' => $profile['id']], [
            'provider_username' => $profile['username'] ?? null, 'name' => $profile['name'] ?? null,
            'user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'status' => 'active', 'connected_at' => now(), 'disconnected_at' => null,
        ]);
        $this->accounts->upsertPage(['social_account_id' => $account->id, 'provider' => 'twitter', 'page_id' => $profile['id']], [
            'page_name' => '@'.($profile['username'] ?? $profile['name']), 'profile_image' => $profile['profile_image_url'] ?? null,
            'page_access_token' => $token['access_token'], 'permissions' => config('twitter.scopes'), 'status' => 'active',
        ]);
        return $account->refresh();
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'twitter');
        if ($post->media->isNotEmpty()) throw new RuntimeException('X media uploads are not enabled yet; publish this X post without attachments.');
        return $this->client->createPost($post, $this->accessToken($page->account), ['text' => $post->message]);
    }

    private function accessToken(SocialAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isFuture()) return $account->user_access_token;
        if (! $account->refresh_token) throw new RuntimeException('Reconnect X: the access token has expired.');
        $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token, 'client_id' => config('twitter.client_id')]);
        $account->update(['user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? $account->refresh_token, 'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null]);
        $account->pages()->get()->each->update(['page_access_token' => $token['access_token']]);
        return $token['access_token'];
    }

    private function token(array $payload): array
    {
        $request = Http::asForm()->timeout(30);
        if (config('twitter.client_secret')) $request = $request->withBasicAuth(config('twitter.client_id'), config('twitter.client_secret'));
        $response = $request->post(config('twitter.api_url').'/2/oauth2/token', $payload);
        if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'error_description', 'X authorization failed.'));
        return $response->json();
    }

    private function redirectUri(): string { return config('twitter.redirect_uri') ?: URL::route('twitter.callback'); }
    private function assertConfigured(): void { if (! config('twitter.client_id')) throw new RuntimeException('Set TWITTER_CLIENT_ID before connecting X.'); }
}
