<?php

namespace Tests\Feature;

use App\Mail\DailySocialPerformanceReport;
use App\Models\Post;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\User;
use App\Services\Social\MetaAnalyticsService;
use Illuminate\Contracts\Cache\Lock as CacheLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DailySocialSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_sends_one_consolidated_email_with_csv_and_blocks_duplicates(): void
    {
        Mail::fake();
        config(['social_reports.enabled' => true, 'social_reports.recipients' => ['manager@example.com'], 'social_reports.timezone' => 'Asia/Kolkata']);

        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Sample Project']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'user-1', 'user_access_token' => 'token']);
        $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'page-1', 'page_name' => 'Sample Page', 'page_access_token' => 'page-token', 'instagram_business_id' => 'ig-1', 'instagram_username' => 'sample.ig', 'status' => 'active']);
        foreach (['facebook', 'instagram'] as $platform) {
            Post::create(['user_id' => $user->id, 'project_id' => $project->id, 'social_page_id' => $page->id, 'platform' => $platform, 'message' => 'Published '.$platform.' post', 'status' => 'published', 'published_at' => '2026-11-01 10:00:00']);
        }

        $facebook = [
            'account' => ['followers_count' => 100],
            'insights' => [
                ['name' => 'page_daily_follows', 'values' => [
                    ['value' => 8, 'end_time' => '2026-11-01T12:00:00+0000'],
                    ['value' => 4, 'end_time' => '2026-10-31T12:00:00+0000'],
                ]],
                ['name' => 'page_daily_unfollows', 'values' => [
                    ['value' => 2, 'end_time' => '2026-11-01T12:00:00+0000'],
                    ['value' => 1, 'end_time' => '2026-10-31T12:00:00+0000'],
                ]],
                ['name' => 'page_media_view', 'values' => [
                    ['value' => 200, 'end_time' => '2026-11-01T12:00:00+0000'],
                    ['value' => 120, 'end_time' => '2026-10-31T12:00:00+0000'],
                ]],
                ['name' => 'page_total_media_view_unique', 'values' => [['value' => 150, 'end_time' => '2026-11-01T12:00:00+0000']]],
            ],
            'media' => [['type' => 'VIDEO', 'published_at' => '2026-11-01T10:00:00+0000']],
        ];
        $instagram = [
            'account' => ['followers_count' => 45, 'media_count' => 12],
            'insights' => [
                ['name' => 'views', 'values' => [['value' => 300, 'end_time' => '2026-11-01T12:00:00+0000']]],
                ['name' => 'reach', 'values' => [['value' => 250, 'end_time' => '2026-11-01T12:00:00+0000']]],
            ],
            'media' => [['type' => 'REELS', 'published_at' => '2026-11-01T09:00:00+0000']],
        ];
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'facebook' && $since === '2026-10-26' && $until === '2026-11-01')->andReturn($facebook);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'instagram' && $since === '2026-10-26' && $until === '2026-11-01')->andReturn($instagram);
        $analytics->shouldReceive('publishedPostCounts')->twice()->andReturn(['total' => 1, 'day' => 1, 'period' => 1]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        Artisan::call('report:send-daily-social-summary', ['--date' => '2026-11-02']);
        Artisan::call('report:send-daily-social-summary', ['--date' => '2026-11-02']);

        Mail::assertSent(DailySocialPerformanceReport::class, function (DailySocialPerformanceReport $mail): bool {
            $this->assertCount(2, $mail->report['rows']);
            $facebookRow = collect($mail->report['rows'])->firstWhere('account_type', 'Facebook');
            $this->assertSame(6.0, $facebookRow['yesterday_net_change'], json_encode($facebookRow));
            $this->assertSame(200, $facebookRow['yesterday_views']);
            $this->assertSame(150, $facebookRow['yesterday_unique_views']);
            $this->assertSame(1, $facebookRow['yesterday_posts']);
            $this->assertSame(1, $facebookRow['total_posts']);
            $this->assertSame(1, $facebookRow['seven_day_posts']);
            $this->assertSame(0, $facebookRow['yesterday_likes']);
            $this->assertSame(0, $facebookRow['seven_day_comments']);
            $csvRow = str_getcsv(explode("\n", trim($mail->csvContent()))[1]);
            $this->assertSame('1', $csvRow[6]);
            $this->assertSame('1', $csvRow[8]);
            $this->assertSame('0', $csvRow[13]);
            $this->assertStringNotContainsString('N/A', $mail->render());
            $this->assertStringNotContainsString('Net Follower Change', $mail->csvContent());
            $this->assertStringNotContainsString('Previous Period Comparison', $mail->csvContent());
            $this->assertStringNotContainsString('Published Content Types', $mail->csvContent());
            return true;
        });
        $this->assertDatabaseHas('daily_social_reports', ['report_date' => '2026-11-02', 'status' => 'sent']);
    }

    public function test_preview_writes_csv_without_sending_email_or_consuming_report_date(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['social_reports.enabled' => false, 'social_reports.timezone' => 'Asia/Kolkata']);
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Preview Project']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'preview-user', 'user_access_token' => 'token']);
        $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'preview-page', 'page_name' => 'Preview Page', 'page_access_token' => 'page-token', 'status' => 'active']);
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform) => $candidate->is($page) && $platform === 'facebook')
            ->andReturn(['account' => ['followers_count' => 42], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->once()->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        Artisan::call('report:send-daily-social-summary', ['--preview' => true, '--date' => '2026-11-02']);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('daily_social_reports', ['report_date' => '2026-11-02']);
        Storage::disk('local')->assertExists('reports/previews/daily-social-performance-2026-11-02.csv');
        $this->assertStringContainsString('Preview Page', Storage::disk('local')->get('reports/previews/daily-social-performance-2026-11-02.csv'));
    }

    public function test_admin_can_send_manual_report_to_configured_recipients_when_scheduled_email_is_disabled(): void
    {
        Mail::fake();
        config(['social_reports.enabled' => false, 'social_reports.recipients' => ['first@example.com', 'second@example.com']]);
        $lock = Mockery::mock(CacheLock::class);
        $lock->shouldReceive('get')->once()->andReturnTrue();
        $lock->shouldReceive('release')->once()->andThrow(new \RuntimeException('Cache backend unavailable'));
        Cache::shouldReceive('lock')->once()->andReturn($lock);
        $user = User::factory()->create(['is_admin' => true]);
        $project = Project::create(['user_id' => $user->id, 'name' => 'Manual Project']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'manual-user', 'user_access_token' => 'token']);
        $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'manual-page', 'page_name' => 'Manual Page', 'page_access_token' => 'page-token', 'status' => 'active']);
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->andReturn(['account' => [], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->once()->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        $response = $this->actingAs($user)->postJson(route('analytics.send-test-report'));

        $response->assertOk()->assertExactJson(['message' => 'Daily social media analytics report has been generated and sent successfully.']);
        Mail::assertSent(DailySocialPerformanceReport::class, function (DailySocialPerformanceReport $mail): bool {
            $this->assertSame(['first@example.com', 'second@example.com'], collect($mail->to)->pluck('address')->all());
            return true;
        });
        $this->assertDatabaseMissing('daily_social_reports', ['status' => 'sent']);
    }

    public function test_non_admin_cannot_send_a_manual_report(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->postJson(route('analytics.send-test-report'))->assertForbidden();
    }

    public function test_manual_report_delivery_failure_returns_error_instead_of_success(): void
    {
        config(['social_reports.enabled' => false, 'social_reports.recipients' => ['manager@example.com']]);
        $user = User::factory()->create(['is_admin' => true]);
        $project = Project::create(['user_id' => $user->id, 'name' => 'Failed Mail Project']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'failed-user', 'user_access_token' => 'token']);
        SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'failed-page', 'page_name' => 'Failed Page', 'page_access_token' => 'page-token', 'status' => 'active']);
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->andReturn(['account' => [], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->once()->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')->once()->andThrow(new \RuntimeException('private smtp connection details'));
        $mailer = Mockery::mock();
        $mailer->shouldReceive('to')->once()->with(['manager@example.com'])->andReturn($pendingMail);
        Mail::swap($mailer);

        $this->actingAs($user)->postJson(route('analytics.send-test-report'))
            ->assertStatus(502)
            ->assertJson(['message' => 'The report email could not be sent. Check the mail configuration and application log.'])
            ->assertDontSee('private smtp connection details');
    }

    public function test_simultaneous_manual_report_request_is_rejected(): void
    {
        config(['social_reports.timezone' => 'Asia/Kolkata', 'social_reports.recipients' => ['manager@example.com']]);
        $user = User::factory()->create(['is_admin' => true]);
        $date = now('Asia/Kolkata')->toDateString();
        $lock = Cache::lock('manual-daily-social-report:'.$date, 900);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($user)->postJson(route('analytics.send-test-report'))
                ->assertStatus(429)
                ->assertJson(['message' => 'A report is already being generated. Please wait before trying again.']);
        } finally {
            $lock->release();
        }
    }
}
