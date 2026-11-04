<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SocialAppCredential;
use App\Models\SocialPage;
use App\Models\User;
use App\Services\Social\InstagramLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_instagram_login_callback_stores_a_standalone_account_and_page(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'BelizeOnTop']);
        SocialAppCredential::create([
            'project_id' => $project->id,
            'provider' => 'instagram',
            'client_id' => 'instagram-client',
            'client_secret' => 'instagram-secret',
            'redirect_uri' => 'https://social.cogniter.com/instagram/callback',
            'status' => 'active',
        ]);

        Http::fake([
            'https://api.instagram.com/oauth/access_token' => Http::response(['access_token' => 'short-token', 'user_id' => 'ig-123'], 200),
            'https://graph.instagram.com/*/access_token*' => Http::response(['access_token' => 'long-token', 'expires_in' => 5184000], 200),
            'https://graph.instagram.com/*/me*' => Http::response([
                'user_id' => 'ig-123', 'username' => 'belizeontop', 'account_type' => 'BUSINESS',
                'media_count' => 27, 'followers_count' => 340, 'profile_picture_url' => 'https://example.test/profile.jpg',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['oauth_project_id' => $project->id, 'instagram_oauth_state' => 'csrf-state'])
            ->get(route('instagram.callback', ['state' => 'csrf-state', 'code' => 'authorization-code']));

        $response->assertRedirect(route('accounts.index', ['project_id' => $project->id]));
        $this->assertDatabaseHas('social_accounts', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'provider_user_id' => 'ig-123',
            'authentication_method' => 'instagram_login',
            'status' => 'active',
        ]);
        $page = SocialPage::where('provider', 'instagram')->where('page_id', 'ig-123')->firstOrFail();
        $this->assertSame('belizeontop', $page->page_name);
        $this->assertSame('long-token', $page->page_access_token);
        $this->assertSame('https://example.test/profile.jpg', $page->profile_image);
    }

    public function test_instagram_callback_rejects_a_mismatched_oauth_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['instagram_oauth_state' => 'expected'])
            ->get(route('instagram.callback', ['state' => 'wrong', 'code' => 'code']))
            ->assertStatus(419);
    }

    public function test_standalone_instagram_reporting_uses_its_token_for_account_and_post_metrics(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'BelizeOnTop']);
        $account = $user->socialAccounts()->create([
            'project_id' => $project->id,
            'provider' => 'instagram',
            'authentication_method' => 'instagram_login',
            'provider_user_id' => 'ig-123',
            'name' => '@belizeontop',
            'user_access_token' => 'standalone-token',
            'token_expires_at' => now()->addDays(50),
            'status' => 'active',
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id,
            'provider' => 'instagram',
            'page_id' => 'ig-123',
            'page_name' => 'belizeontop',
            'page_access_token' => 'standalone-token',
            'status' => 'active',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_ends_with(parse_url($url, PHP_URL_PATH), '/ig-123')) {
                return Http::response(['user_id' => 'ig-123', 'username' => 'belizeontop', 'media_count' => 27, 'followers_count' => 340], 200);
            }
            if (str_contains($url, '/ig-123/insights')) {
                $metric = $request['metric'];
                return Http::response(['data' => [['name' => $metric, 'values' => [['value' => 9, 'end_time' => '2026-11-04T00:00:00+0000']]]]], 200);
            }
            if (str_contains($url, '/ig-123/media')) {
                return Http::response(['data' => [[
                    'id' => 'media-1', 'timestamp' => '2026-11-04T10:00:00+05:30', 'like_count' => 8, 'comments_count' => 2,
                ]]], 200);
            }
            if (str_contains($url, '/media-1/insights')) {
                return Http::response(['data' => array_map(fn ($name) => ['name' => $name, 'total_value' => ['value' => 3]], ['views', 'reach', 'saved', 'shares'])], 200);
            }
            return Http::response(['data' => []], 200);
        });

        $data = app(InstagramLoginService::class)->dashboardForDailyReport($page, '2026-10-29', '2026-11-04');

        $this->assertSame(340, $data['account']['followers_count']);
        $this->assertSame(27, $data['account']['media_count']);
        $this->assertCount(1, $data['media']);
        $this->assertSame(8, $data['media'][0]['metrics']['likes']);
        $this->assertSame(3, $data['media'][0]['metrics']['saved']);
        $this->assertNotNull($page->fresh()->last_synced_at);
    }

    public function test_invalid_instagram_token_is_refreshed_and_saved_before_retrying(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Token refresh']);
        $account = $user->socialAccounts()->create([
            'project_id' => $project->id, 'provider' => 'instagram', 'provider_user_id' => 'ig-refresh',
            'user_access_token' => 'rejected-token', 'token_expires_at' => now()->addDays(50),
            'status' => 'active', 'connected_at' => now()->subDays(3),
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id, 'provider' => 'instagram', 'page_id' => 'ig-refresh',
            'page_name' => 'refresh_test', 'page_access_token' => 'rejected-token', 'status' => 'active',
        ]);
        $profileCalls = 0;
        Http::fake(function ($request) use (&$profileCalls) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/refresh_access_token')) {
                return Http::response(['access_token' => 'refreshed-token', 'expires_in' => 5184000], 200);
            }
            if (str_ends_with($path, '/ig-refresh')) {
                $profileCalls++;
                if ($profileCalls === 1) return Http::response(['error' => ['message' => 'Invalid OAuth 2.0 Access Token', 'code' => 190]], 401);
                return Http::response(['user_id' => 'ig-refresh', 'username' => 'refresh_test', 'followers_count' => 10, 'media_count' => 2], 200);
            }
            if (str_contains($path, '/insights')) return Http::response(['data' => []], 200);
            if (str_ends_with($path, '/media')) return Http::response(['data' => []], 200);
            return Http::response(['data' => []], 200);
        });

        $data = app(InstagramLoginService::class)->dashboardForDailyReport($page, '2026-10-29', '2026-11-04');

        $this->assertSame(10, $data['account']['followers_count']);
        $this->assertSame('refreshed-token', $account->fresh()->user_access_token);
        $this->assertSame('refreshed-token', $page->fresh()->page_access_token);
        $this->assertSame('active', $account->fresh()->status);
        $this->assertSame(2, $profileCalls);
    }

    public function test_belizeontop_and_groquickly_media_are_paginated_by_their_own_instagram_ids(): void
    {
        $user = User::factory()->create();
        $pages = [];
        foreach ([['BelizeOnTop', 'ig-belize'], ['GroQuickly', 'ig-gro']] as [$projectName, $instagramId]) {
            $project = Project::create(['user_id' => $user->id, 'name' => $projectName]);
            $account = $user->socialAccounts()->create([
                'project_id' => $project->id, 'provider' => 'instagram', 'authentication_method' => 'instagram_login',
                'provider_user_id' => $instagramId, 'user_access_token' => 'shared-app-token',
                'token_expires_at' => now()->addDays(50), 'status' => 'active',
            ]);
            $pages[] = SocialPage::create([
                'social_account_id' => $account->id, 'provider' => 'instagram', 'page_id' => $instagramId,
                'page_name' => $projectName, 'page_access_token' => 'shared-app-token', 'status' => 'active',
            ]);
        }

        $mediaPageCalls = [];
        Http::fake(function ($request) use (&$mediaPageCalls) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (preg_match('~/v23\.0/(ig-belize|ig-gro)$~', $path, $match)) {
                return Http::response(['user_id' => $match[1], 'username' => $match[1], 'followers_count' => 100, 'media_count' => 2], 200);
            }
            if (str_ends_with($path, '/insights')) return Http::response(['data' => [['name' => $request['metric'], 'values' => [['value' => 5, 'end_time' => '2026-11-04T00:00:00+0000']]]]], 200);
            if (str_ends_with($path, '/media')) {
                $id = str_contains($path, 'ig-belize') ? 'ig-belize' : 'ig-gro';
                $mediaPageCalls[$id] = ($mediaPageCalls[$id] ?? 0) + 1;
                if ($mediaPageCalls[$id] > 1) {
                    return Http::response(['data' => [['id' => $id.'-post-2', 'timestamp' => '2026-11-03T10:00:00+00:00', 'media_type' => 'IMAGE', 'like_count' => 0, 'comments_count' => 0]]], 200);
                }
                return Http::response(['data' => [['id' => $id.'-post-1', 'timestamp' => '2026-11-04T10:00:00+00:00', 'media_type' => 'IMAGE', 'like_count' => 1, 'comments_count' => 2]], 'paging' => ['next' => 'https://graph.instagram.com/v23.0/'.$id.'/media?after=next']], 200);
            }
            if (str_contains($path, '/insights')) return Http::response(['data' => [['name' => $request['metric'], 'total_value' => ['value' => 1]]]], 200);
            return Http::response(['data' => []], 200);
        });

        foreach ($pages as $page) {
            $data = app(InstagramLoginService::class)->dashboardForDailyReport($page, '2026-11-03', '2026-11-04');
            $this->assertCount(2, $data['media']);
            $this->assertSame(100, $data['account']['followers_count']);
            $this->assertSame($page->page_id.'-post-1', $data['media'][0]['id']);
            $this->assertSame($page->page_id.'-post-2', $data['media'][1]['id']);
        }
    }
}
