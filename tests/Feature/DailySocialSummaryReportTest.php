<?php

namespace Tests\Feature;

use App\Mail\DailySocialPerformanceReport;
use App\Models\Post;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\User;
use App\Services\AnalyticsReportSummaryService;
use App\Services\Social\MetaAnalyticsService;
use App\Services\Social\InstagramLoginService;
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

    protected function setUp(): void
    {
        parent::setUp();
        $summary = Mockery::mock(AnalyticsReportSummaryService::class);
        $summary->shouldReceive('generate')->andReturnUsing(fn (array $columns, array $keys, array $rows) => collect($rows)
            ->map(fn (array $row) => $row['account_name'].' ('.$row['account_type'].')')
            ->join(' and ').' performance summary.');
        $this->app->instance(AnalyticsReportSummaryService::class, $summary);
    }

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
                    ['value' => 50, 'end_time' => '2026-11-02T12:00:00+0000'],
                    ['value' => 120, 'end_time' => '2026-10-31T12:00:00+0000'],
                ]],
                ['name' => 'page_total_media_view_unique', 'values' => [['value' => 150, 'end_time' => '2026-11-01T12:00:00+0000']]],
                ['name' => 'page_total_media_view_unique_week', 'values' => [['value' => 180, 'end_time' => '2026-11-02T12:00:00+0000']]],
            ],
            'media' => [
                ['type' => 'VIDEO', 'published_at' => '2026-11-01T10:00:00+0000', 'metrics' => ['reactions' => 0, 'comments' => 0, 'shares' => 1]],
                ['type' => 'VIDEO', 'published_at' => '2026-11-02T10:00:00+0000', 'metrics' => ['reactions' => 0, 'comments' => 0]],
            ],
        ];
        $instagram = [
            'account' => ['followers_count' => 45, 'media_count' => 12],
            'insights' => [
                ['name' => 'views', 'values' => [['value' => 300, 'end_time' => '2026-11-01T12:00:00+0000']]],
                ['name' => 'reach', 'values' => [['value' => 250, 'end_time' => '2026-11-01T12:00:00+0000']]],
                ['name' => 'reach_week', 'values' => [['value' => 260, 'end_time' => '2026-11-02T12:00:00+0000']]],
            ],
            'media' => [['type' => 'REELS', 'published_at' => '2026-11-01T09:00:00+0000', 'metrics' => ['likes' => 0, 'comments' => 0, 'shares' => 0, 'saved' => 0]]],
        ];
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'facebook' && $since === '2026-10-27' && $until === '2026-11-02')->andReturn($facebook);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'instagram' && $since === '2026-10-27' && $until === '2026-11-02')->andReturn($instagram);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'facebook' && $since === '2026-10-20' && $until === '2026-10-26')->andReturn($facebook);
        $analytics->shouldReceive('dashboardForDailyReport')->once()->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && $platform === 'instagram' && $since === '2026-10-20' && $until === '2026-10-26')->andReturn($instagram);
        $analytics->shouldReceive('publishedPostCounts')->twice()
            ->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && in_array($platform, ['facebook', 'instagram'], true) && $since === '2026-10-27' && $until === '2026-11-02')
            ->andReturn(['total' => 1, 'day' => 1, 'period' => 1]);
        $analytics->shouldReceive('publishedPostCounts')->twice()
            ->withArgs(fn ($candidate, $platform, $since, $until) => $candidate->is($page) && in_array($platform, ['facebook', 'instagram'], true) && $since === '2026-10-20' && $until === '2026-10-26')
            ->andReturn(['total' => 1, 'day' => 1, 'period' => 1]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        Artisan::call('report:send-daily-social-summary', ['--date' => '2026-11-02']);
        Artisan::call('report:send-daily-social-summary', ['--date' => '2026-11-02']);

        Mail::assertSent(DailySocialPerformanceReport::class, function (DailySocialPerformanceReport $mail): bool {
            $this->assertCount(2, $mail->report['rows']);
            $facebookRow = collect($mail->report['rows'])->firstWhere('account_type', 'Facebook');
            $instagramRow = collect($mail->report['rows'])->firstWhere('account_type', 'Instagram');
            $this->assertSame(6.0, $facebookRow['yesterday_net_change'], json_encode($facebookRow));
            $this->assertSame('2026-10-27', $mail->report['current_period']['since']);
            $this->assertSame('2026-11-02', $mail->report['current_period']['until']);
            $this->assertSame('2026-10-20', $mail->report['previous_period']['since']);
            $this->assertSame('2026-10-26', $mail->report['previous_period']['until']);
            $this->assertSame(12.0, $facebookRow['comparison']['current']['follows']);
            $this->assertSame(3.0, $facebookRow['comparison']['current']['unfollows']);
            $this->assertNull($facebookRow['comparison']['previous']['followers']);
            $this->assertStringNotContainsString('historical data unavailable', strtolower($mail->render()));
            $this->assertSame(200, $facebookRow['yesterday_views']);
            $this->assertSame(370.0, $facebookRow['seven_day_views']);
            $this->assertSame(150, $facebookRow['yesterday_unique_views']);
            $this->assertSame(180, $facebookRow['seven_day_unique_views']);
            $this->assertSame(260, $instagramRow['seven_day_unique_views']);
            $this->assertSame(1, $facebookRow['yesterday_posts']);
            $this->assertSame(1, $facebookRow['total_posts']);
            $this->assertSame(1, $facebookRow['seven_day_posts']);
            $this->assertSame(0.0, $facebookRow['yesterday_likes']);
            $this->assertSame(1.0, $facebookRow['yesterday_shares']);
            $this->assertSame(0.0, $facebookRow['seven_day_comments']);
            $csvRow = str_getcsv(explode("\n", trim($mail->csvContent()))[1]);
            $this->assertSame('Sample Page', $csvRow[0]);
            $this->assertSame('1', $csvRow[5]);
            $this->assertSame('370', $csvRow[7]);
            $this->assertSame('0', $csvRow[12]);
            $this->assertStringNotContainsString('Metric Availability', $mail->csvContent());
            $this->assertStringNotContainsString('Unfollows', $mail->csvContent());
            $this->assertStringNotContainsString('Project and account performance', $mail->render());
            $this->assertStringContainsString('Please find the social media report below.', $mail->render());
            $this->assertStringContainsString('Oct 27, 2026 to Nov 2, 2026', $mail->report['period_label']);
            $this->assertStringContainsString('Last 7 Days Unique Views', $mail->render());
            $this->assertStringNotContainsString('N/A', $mail->render());
            $this->assertSame(['Facebook', 'Instagram'], collect($mail->report['rows'])->pluck('account_type')->all());
            $this->assertCount(1, $mail->report['brand_summaries']);
            $this->assertStringContainsString('Sample Page', $mail->render());
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
        $analytics->shouldReceive('dashboardForDailyReport')->twice()->withArgs(fn ($candidate, $platform) => $candidate->is($page) && $platform === 'facebook')
            ->andReturn(['account' => ['followers_count' => 42], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->twice()->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        $exitCode = Artisan::call('report:send-daily-social-summary', ['--preview' => true, '--date' => '2026-11-02']);
        $this->assertSame(0, $exitCode, Artisan::output());

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('daily_social_reports', ['report_date' => '2026-11-02']);
        Storage::disk('local')->assertExists('reports/previews/daily-social-performance-2026-11-02.csv');
        $this->assertStringContainsString('Preview Page', Storage::disk('local')->get('reports/previews/daily-social-performance-2026-11-02.csv'));
    }

    public function test_report_rows_follow_the_requested_brand_and_platform_order(): void
    {
        Mail::fake();
        config(['social_reports.recipients' => ['manager@example.com'], 'social_reports.timezone' => 'Asia/Kolkata']);
        $user = User::factory()->create();
        $accounts = [];
        foreach (['Tourist Point', 'Wheels Belize', 'Belize On Top'] as $brand) {
            $project = Project::create(['user_id' => $user->id, 'name' => $brand]);
            $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'user-'.$brand, 'user_access_token' => 'token']);
            $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'page-'.$brand, 'page_name' => $brand, 'page_access_token' => 'page-token', 'instagram_business_id' => 'ig-'.$brand, 'instagram_username' => strtolower(str_replace(' ', '', $brand)), 'status' => 'active']);
            $accounts[] = $page;
        }

        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->times(12)->andReturn(['account' => ['followers_count' => 10, 'media_count' => 0], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->times(12)->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);
        $this->app->instance(InstagramLoginService::class, Mockery::mock(InstagramLoginService::class));

        Artisan::call('report:send-daily-social-summary', ['--manual' => true, '--date' => '2026-11-04']);

        Mail::assertSent(DailySocialPerformanceReport::class, function (DailySocialPerformanceReport $mail): bool {
            $this->assertSame([
                'Belize On Top|Facebook', 'Belize On Top|Instagram',
                'Wheels Belize|Facebook', 'Wheels Belize|Instagram',
                'Tourist Point|Facebook', 'Tourist Point|Instagram',
            ], collect($mail->report['rows'])->map(fn ($row) => $row['account_name'].'|'.$row['account_type'])->all());
            $rendered = $mail->render();
            $this->assertLessThan(strpos($rendered, 'Wheels Belize'), strpos($rendered, 'Belize On Top'));
            $this->assertLessThan(strpos($rendered, 'Tourist Point'), strpos($rendered, 'Wheels Belize'));
            return true;
        });
    }

    public function test_today_views_use_daily_page_insights_even_when_no_post_was_published_today(): void
    {
        Mail::fake();
        config(['social_reports.recipients' => ['manager@example.com'], 'social_reports.timezone' => 'Asia/Kolkata']);
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Today Metrics']);
        $account = SocialAccount::create(['user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook', 'provider_user_id' => 'today-user', 'user_access_token' => 'token']);
        $page = SocialPage::create(['social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => 'today-page', 'page_name' => 'Today Page', 'page_access_token' => 'page-token', 'status' => 'active']);
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $analytics->shouldReceive('dashboardForDailyReport')->twice()->andReturn([
            'account' => ['followers_count' => 124],
            'insights' => [
                ['name' => 'page_media_view', 'values' => [['value' => 30, 'end_time' => '2026-11-03T12:00:00+0000'], ['value' => 54, 'end_time' => '2026-11-04T12:00:00+0000']]],
                ['name' => 'page_total_media_view_unique', 'values' => [['value' => 16, 'end_time' => '2026-11-04T12:00:00+0000']]],
            ],
            'media' => [],
        ]);
        $analytics->shouldReceive('publishedPostCounts')->twice()->andReturn(['total' => 3, 'day' => 0, 'period' => 3]);
        $this->app->instance(MetaAnalyticsService::class, $analytics);

        Artisan::call('report:send-daily-social-summary', ['--manual' => true, '--date' => '2026-11-04']);

        Mail::assertSent(DailySocialPerformanceReport::class, function (DailySocialPerformanceReport $mail): bool {
            $row = $mail->report['rows'][0];
            $this->assertSame(54, $row['yesterday_views']);
            $this->assertSame(16, $row['yesterday_unique_views']);
            $this->assertSame('0', \App\Support\ReportMetric::display($row['seven_day_unique_views']));
            return true;
        });
    }

    public function test_manual_email_contains_data_from_standalone_instagram_account(): void
    {
        Mail::fake();
        config(['social_reports.recipients' => ['manager@example.com'], 'social_reports.timezone' => 'Asia/Kolkata']);
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Bollies']);
        $account = SocialAccount::create([
            'user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'instagram',
            'provider_user_id' => 'ig-bollies', 'user_access_token' => 'token', 'status' => 'active',
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id, 'provider' => 'instagram', 'page_id' => 'ig-bollies',
            'page_name' => 'belizeontop', 'instagram_username' => 'belizeontop', 'page_access_token' => 'token', 'status' => 'active',
        ]);
        $analytics = Mockery::mock(MetaAnalyticsService::class);
        $this->app->instance(MetaAnalyticsService::class, $analytics);
        $instagram = Mockery::mock(InstagramLoginService::class);
        $instagram->shouldReceive('dashboardForDailyReport')->times(3)->withArgs(fn ($candidate) => $candidate->is($page))->andReturn([
            'account' => ['followers_count' => 625, 'media_count' => 14],
            'insights' => [['name' => 'views', 'values' => [['value' => 89, 'end_time' => '2026-11-04T00:00:00+0000']]]],
            'media' => [['id' => 'media-bollies', 'published_at' => '2026-11-04T11:00:00+0530', 'metrics' => ['likes' => 21, 'comments' => 3, 'saved' => 2, 'shares' => 1]]],
            'insights_unavailable' => [],
        ], [
            'account' => ['followers_count' => 625, 'media_count' => 14],
            'insights' => [['name' => 'views', 'values' => [['value' => 89, 'end_time' => '2026-11-04T00:00:00+0000']]]],
            'media' => [], 'insights_unavailable' => [],
        ]);
        $this->app->instance(InstagramLoginService::class, $instagram);

        Artisan::call('report:send-daily-social-summary', ['--manual' => true, '--date' => '2026-11-04']);

        Mail::assertSent(DailySocialPerformanceReport::class, function (DailySocialPerformanceReport $mail) use ($page): bool {
            $row = collect($mail->report['rows'])->firstWhere('account_name', 'Belize On Top');
            $this->assertNotNull($row);
            $this->assertSame('Available', $row['metric_availability']);
            $this->assertSame(625, $row['followers']);
            $this->assertSame(89.0, $row['seven_day_views']);
            $this->assertSame(1, $row['yesterday_posts']);
            $this->assertSame(21.0, $row['yesterday_likes']);
            $this->assertStringNotContainsString('N/A', $mail->csvContent());
            $this->assertStringContainsString('0', $mail->csvContent());
            $this->assertStringContainsString('Belize On Top', $mail->render());
            return true;
        });
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
        $analytics->shouldReceive('dashboardForDailyReport')->twice()->andReturn(['account' => [], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->twice()->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
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
        $analytics->shouldReceive('dashboardForDailyReport')->twice()->andReturn(['account' => [], 'insights' => [], 'media' => []]);
        $analytics->shouldReceive('publishedPostCounts')->twice()->andReturn(['total' => 0, 'day' => 0, 'period' => 0]);
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
        $lock = Cache::lock('manual-daily-social-report:'.$date, 600);
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
