<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\User;
use App\Services\Social\MetaAnalyticsService;
use App\Services\Social\InstagramLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_date_range_requests_an_equal_previous_period(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Analytics']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'user-1', 'user_access_token' => 'token']);
        $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'page-1', 'page_name' => 'Test Page', 'page_access_token' => 'page-token', 'status' => 'active']);
        $payload = ['account' => ['id' => 'page-1', 'name' => 'Test Page', 'fan_count' => 100], 'insights' => [], 'media' => [['id' => 'post-1', 'text' => 'Sample post', 'published_at' => null, 'type' => 'FEED', 'url' => null, 'metrics' => []]]];
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboard')->twice()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'facebook' && in_array([$since, $until], [['2026-10-20', '2026-10-22'], ['2026-10-17', '2026-10-19']], true))->andReturn($payload);
        $analytics->shouldReceive('publishedPostCounts')->twice()->andReturn(['period' => 1]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        $response = $this->actingAs($user)->get(route('accounts.analytics', ['page' => $page, 'platform' => 'facebook', 'account_id' => $page->id, 'range' => 'custom', 'since' => '2026-10-20', 'until' => '2026-10-22']));

        $response->assertOk()->assertSee('Facebook performance')->assertSee('Sample post')->assertDontSee('Check the connected account token');
    }

    public function test_instagram_insights_accept_total_value_and_daily_reach_responses(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Instagram']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'user-ig', 'user_access_token' => 'token']);
        $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'page-ig', 'page_name' => 'Instagram Page', 'page_access_token' => 'page-token', 'instagram_business_id' => 'ig-1', 'instagram_username' => 'mgrey3408', 'status' => 'active']);
        $sequence = Http::sequence()
            ->push([
                'id' => 'ig-1', 'username' => 'mgrey3408', 'followers_count' => 1, 'media_count' => 12,
            ])
            ->push(['data' => [['name' => 'views', 'total_value' => ['value' => 55]]]])
            ->push(['data' => [['name' => 'reach', 'values' => [
                ['value' => 2, 'end_time' => '2026-10-28T00:00:00+0000'],
                ['value' => 3, 'end_time' => '2026-10-29T00:00:00+0000'],
            ]]]])
            ->push(['data' => [['name' => 'accounts_engaged', 'total_value' => ['value' => 7]]]])
            ->push(['data' => [['name' => 'follower_count', 'total_value' => ['value' => 1]]]])
            ->push(['data' => []])
            ->push(['data' => []]);
        Http::fake(['graph.facebook.com/*' => $sequence]);

        $data = app(MetaAnalyticsService::class)->dashboard($page, 'instagram', '2026-07-01', '2026-10-29');

        $this->assertSame(55, $data['insights'][0]['values'][0]['value']);
        $this->assertCount(2, $data['insights'][1]['values']);
        $this->assertSame(7, $data['insights'][2]['values'][0]['value']);
        $this->assertSame('mgrey3408', $data['account']['username']);
        $this->assertSame(12, $data['account']['media_count']);
        $this->assertTrue($data['insights_window_limited']);
        $this->assertSame('2026-08-01', $data['insights_since']);
    }

    public function test_standalone_instagram_account_opens_analytics_dashboard(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Standalone Instagram']);
        $account = SocialAccount::create([
            'user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'instagram',
            'provider_user_id' => 'ig-standalone', 'user_access_token' => 'token', 'status' => 'reauthorization_required',
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id, 'provider' => 'instagram', 'page_id' => 'ig-standalone',
            'page_name' => 'belizeontop', 'page_access_token' => 'token', 'status' => 'active',
        ]);
        $data = [
            'account' => ['followers_count' => 340, 'media_count' => 27],
            'insights' => [['name' => 'views', 'values' => [['value' => 54, 'end_time' => '2026-11-04T00:00:00+0000']]]],
            'media' => [['id' => 'm1', 'text' => 'Latest post', 'type' => 'IMAGE', 'published_at' => '2026-11-04T10:00:00+0000', 'url' => null, 'metrics' => ['likes' => 8, 'comments' => 2]]],
        ];
        $instagram = Mockery::mock(InstagramLoginService::class);
        $instagram->shouldReceive('dashboard')->twice()->andReturn($data);
        $this->app->instance(InstagramLoginService::class, $instagram);

        $this->actingAs($user)
            ->get(route('accounts.analytics', ['page' => $page, 'platform' => 'instagram', 'range' => 'today']))
            ->assertOk()
            ->assertSee('@belizeontop')
            ->assertSee('Latest post')
            ->assertSee('340');
    }

    public function test_accounts_screen_shows_a_professional_dashboard_link_for_standalone_instagram(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Standalone Instagram']);
        $account = SocialAccount::create([
            'user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'instagram',
            'provider_user_id' => 'ig-standalone', 'user_access_token' => 'token', 'status' => 'reauthorization_required',
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id, 'provider' => 'instagram', 'page_id' => 'ig-standalone',
            'page_name' => 'belizeontop', 'page_access_token' => 'token', 'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('accounts.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertSee('Instagram Professional Dashboard')
            ->assertSee(route('accounts.analytics', ['page' => $page, 'platform' => 'instagram', 'project_id' => $project->id]), false);
    }

    public function test_instagram_dashboard_error_offers_reconnect_action(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Standalone Instagram']);
        $account = SocialAccount::create([
            'user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'instagram',
            'provider_user_id' => 'ig-standalone', 'user_access_token' => 'token', 'status' => 'reauthorization_required',
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id, 'provider' => 'instagram', 'page_id' => 'ig-standalone',
            'page_name' => 'belizeontop', 'page_access_token' => 'token', 'status' => 'active',
        ]);
        $instagram = Mockery::mock(InstagramLoginService::class);
        $instagram->shouldReceive('dashboard')->once()->andThrow(new \RuntimeException('Instagram still rejects the refreshed token. Reconnect the Instagram account.'));
        $this->app->instance(InstagramLoginService::class, $instagram);

        $this->actingAs($user)
            ->get(route('accounts.analytics', ['page' => $page, 'platform' => 'instagram']))
            ->assertOk()
            ->assertSee('Meta rejected the Instagram token')
            ->assertSee(route('instagram.redirect', ['project_id' => $project->id]), false);
    }
}
