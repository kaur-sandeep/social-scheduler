<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Services\Social\MetaAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaPublishedPostCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_counts_use_graph_summary_for_total_and_date_windows(): void
    {
        config(['facebook.graph_version' => 'v23.0']);
        Http::fakeSequence()
            ->push(['data' => [], 'summary' => ['total_count' => 24]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 1]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 3]], 200);
        $page = $this->makePage('facebook-page-id');

        $counts = app(MetaAnalyticsService::class)->publishedPostCounts(
            $page, 'facebook', '2026-10-27', '2026-11-02', '2026-11-03', 'Asia/Kolkata'
        );

        $this->assertSame(['total' => 24, 'day' => 1, 'period' => 3], $counts);
        Http::assertSentCount(3);
        $urls = Http::recorded()->map(fn (array $pair) => $pair[0]->url())->all();
        $summaryRequest = collect($urls)->contains(function (string $url): bool {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            return str_contains($url, '/facebook-page-id/published_posts')
                && ($query['summary'] ?? null) === 'total_count' && (int) ($query['since'] ?? 0) === 1
                && ($query['access_token'] ?? null) === 'page-token';
        });
        $this->assertTrue($summaryRequest, json_encode($urls));
        $dateRangeRequest = collect($urls)->contains(function (string $url): bool {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            return isset($query['until'])
                && (int) ($query['since'] ?? 0) === Carbon::parse('2026-10-27', 'Asia/Kolkata')->startOfDay()->timestamp
                && (int) $query['until'] === Carbon::parse('2026-11-02', 'Asia/Kolkata')->endOfDay()->timestamp;
        });
        $this->assertTrue($dateRangeRequest, json_encode($urls));
    }

    public function test_daily_report_fetch_includes_posts_published_late_on_the_last_day(): void
    {
        config(['facebook.graph_version' => 'v23.0', 'app.timezone' => 'Asia/Kolkata']);
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_ends_with($path, '/published_posts')) {
                return Http::response(['data' => [[
                    'id' => 'late-post',
                    'created_time' => '2026-11-02T19:11:00+0000',
                    'shares' => ['count' => 1],
                    'comments' => ['summary' => ['total_count' => 0]],
                    'reactions' => ['summary' => ['total_count' => 1]],
                ]]], 200);
            }

            if (str_ends_with($path, '/late-post/insights')) return Http::response(['data' => []], 200);
            if (str_ends_with($path, '/insights')) return Http::response(['data' => []], 200);
            if (($query['fields'] ?? null) === 'followers_count') return Http::response(['followers_count' => 124], 200);
            return Http::response(['id' => 'facebook-page-id', 'name' => 'Test Page', 'fan_count' => 124], 200);
        });

        $page = $this->makePage('facebook-page-id');
        $data = app(MetaAnalyticsService::class)->dashboardForDailyReport($page, 'facebook', '2026-10-27', '2026-11-02');

        $this->assertSame(1, data_get($data, 'media.0.metrics.shares'));
        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/facebook-page-id/published_posts')) return false;
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            return (int) ($query['since'] ?? 0) === Carbon::parse('2026-10-27', 'Asia/Kolkata')->startOfDay()->timestamp
                && (int) ($query['until'] ?? 0) === Carbon::parse('2026-11-02', 'Asia/Kolkata')->endOfDay()->timestamp;
        });
    }

    public function test_instagram_date_counts_paginate_only_media_ids_and_timestamps(): void
    {
        config(['facebook.graph_version' => 'v23.0']);
        Http::fakeSequence()
            ->push(['data' => [
                ['id' => 'ig-1', 'timestamp' => '2026-11-01T10:00:00+0000'],
                ['id' => 'ig-2', 'timestamp' => '2026-10-26T10:00:00+0000'],
            ], 'paging' => ['next' => 'https://graph.facebook.com/v23.0/instagram-user-id/media?after=next'],], 200)
            ->push(['data' => [
                ['id' => 'ig-3', 'timestamp' => '2026-10-29T10:00:00+0000'],
                ['id' => 'ig-1', 'timestamp' => '2026-11-01T10:00:00+0000'],
            ]], 200);
        $page = $this->makePage('facebook-page-id', 'instagram-user-id');

        $counts = app(MetaAnalyticsService::class)->publishedPostCounts(
            $page, 'instagram', '2026-10-27', '2026-11-02', '2026-11-03', 'Asia/Kolkata', 85
        );

        $this->assertSame(['total' => 85, 'day' => 0, 'period' => 2], $counts);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            return str_contains($request->url(), '/instagram-user-id/media')
                && ($query['fields'] ?? null) === 'id,timestamp'
                && ! str_contains((string) parse_url($request->url(), PHP_URL_QUERY), 'media_url');
        });
    }

    public function test_facebook_count_falls_back_to_paginating_post_metadata_when_summary_is_missing(): void
    {
        config(['facebook.graph_version' => 'v23.0']);
        Http::fakeSequence()
            ->push(['data' => [], 'paging' => ['next' => 'https://graph.facebook.com/v23.0/facebook-page-id/published_posts?after=first']], 200)
            ->push(['data' => [
                ['id' => 'fb-1', 'created_time' => '2026-11-01T10:00:00+0000'],
            ], 'paging' => ['next' => 'https://graph.facebook.com/v23.0/facebook-page-id/published_posts?after=second']], 200)
            ->push(['data' => [
                ['id' => 'fb-2', 'created_time' => '2026-10-29T10:00:00+0000'],
            ]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 0]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 2]], 200);
        $page = $this->makePage('facebook-page-id');

        $counts = app(MetaAnalyticsService::class)->publishedPostCounts(
            $page, 'facebook', '2026-10-27', '2026-11-02', '2026-11-03', 'Asia/Kolkata'
        );

        $this->assertSame(['total' => 2, 'day' => 0, 'period' => 2], $counts);
        Http::assertSentCount(5);
        $lifetimeFallbackIncludesRequiredSince = Http::recorded()->map(fn (array $pair) => $pair[0])->contains(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            return str_contains($request->url(), '/facebook-page-id/published_posts')
                && ($query['fields'] ?? null) === 'id,created_time'
                && (int) ($query['since'] ?? 0) === 1;
        });
        $this->assertTrue($lifetimeFallbackIncludesRequiredSince);
    }

    public function test_facebook_count_paginates_when_meta_summary_is_capped_at_25(): void
    {
        config(['facebook.graph_version' => 'v23.0']);
        $firstPage = array_map(fn (int $id) => ['id' => 'fb-'.$id], range(1, 100));
        Http::fakeSequence()
            ->push(['data' => [], 'summary' => ['total_count' => 25]], 200)
            ->push(['data' => $firstPage, 'paging' => ['next' => 'https://graph.facebook.com/v23.0/facebook-page-id/published_posts?after=next']], 200)
            ->push(['data' => [['id' => 'fb-101']]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 0]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 1]], 200);
        $page = $this->makePage('facebook-page-id');

        $counts = app(MetaAnalyticsService::class)->publishedPostCounts(
            $page, 'facebook', '2026-10-27', '2026-11-02', '2026-11-03', 'Asia/Kolkata'
        );

        $this->assertSame(['total' => 101, 'day' => 0, 'period' => 1], $counts);
        Http::assertSentCount(5);
    }

    public function test_facebook_keeps_a_valid_summary_if_metadata_pagination_fails(): void
    {
        config(['facebook.graph_version' => 'v23.0']);
        Http::fakeSequence()
            ->push(['data' => [], 'summary' => ['total_count' => 25]], 200)
            ->push(['error' => ['message' => 'Permission denied']], 400)
            ->push(['data' => [], 'summary' => ['total_count' => 0]], 200)
            ->push(['data' => [], 'summary' => ['total_count' => 1]], 200);
        $page = $this->makePage('facebook-page-id');

        $counts = app(MetaAnalyticsService::class)->publishedPostCounts(
            $page, 'facebook', '2026-10-27', '2026-11-02', '2026-11-03', 'Asia/Kolkata'
        );

        $this->assertSame(['total' => 25, 'day' => 0, 'period' => 1], $counts);
    }

    private function makePage(string $pageId, ?string $instagramId = null): SocialPage
    {
        $user = \App\Models\User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Post Count Project']);
        $account = SocialAccount::create([
            'user_id' => $user->id, 'project_id' => $project->id, 'provider' => 'facebook',
            'provider_user_id' => 'meta-user-'.$pageId, 'user_access_token' => 'user-token',
        ]);
        return SocialPage::create([
            'social_account_id' => $account->id, 'provider' => 'facebook', 'page_id' => $pageId,
            'page_name' => 'BelizeOnTop', 'page_access_token' => 'page-token',
            'instagram_business_id' => $instagramId, 'status' => 'active',
        ]);
    }
}
