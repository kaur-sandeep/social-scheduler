<?php

namespace App\Console\Commands;

use App\Mail\DailySocialPerformanceReport;
use App\Models\DailySocialReport;
use App\Models\SocialPage;
use App\Services\AnalyticsReportSummaryService;
use App\Services\Social\MetaAnalyticsService;
use App\Services\Social\InstagramLoginService;
use App\Support\ReportMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendDailySocialSummary extends Command
{
    protected $signature = 'report:send-daily-social-summary {--preview : Generate a local sample report and CSV without emailing or recording it} {--date= : Report date in YYYY-MM-DD format} {--manual : Send now without requiring scheduled reports to be enabled} {--to= : Comma or semicolon separated recipient override for a manual send}';
    protected $description = 'Email one consolidated daily Facebook and Instagram performance report';

    private const COLUMNS = [
        'Account Name', 'Account Type', 'Metric Availability', 'Total Followers',
        'Yesterday Follows', 'Yesterday Unfollows',
        'Yesterday Published Posts', 'Last 7 Days Published Posts',
        'Yesterday Views', 'Last 7 Days Views',
        'Yesterday Unique Views', 'Last 7 Days Unique Views',
        "Yesterday Likes / Reactions on Published Posts", 'Last 7 Days Likes / Reactions on Published Posts',
        'Yesterday Comments on Published Posts', 'Last 7 Days Comments on Published Posts',
        'Yesterday Shares on Published Posts', 'Last 7 Days Shares on Published Posts',
        'Yesterday Saves on Published Posts', 'Last 7 Days Saves on Published Posts',
    ];

    private const COLUMN_KEYS = [
        'account_name', 'account_type', 'metric_availability', 'followers', 'yesterday_follows',
        'yesterday_unfollows', 'yesterday_posts', 'seven_day_posts',
        'yesterday_views', 'seven_day_views', 'yesterday_unique_views', 'seven_day_unique_views',
        'yesterday_likes', 'seven_day_likes', 'yesterday_comments', 'seven_day_comments',
        'yesterday_shares', 'seven_day_shares', 'yesterday_saves', 'seven_day_saves',
    ];

    public function handle(MetaAnalyticsService $analytics, AnalyticsReportSummaryService $summaryService, InstagramLoginService $instagram): int
    {
        $timezone = config('social_reports.timezone', 'Asia/Kolkata');
        try {
            $reportDate = $this->option('date')
                ? Carbon::parse($this->option('date'), $timezone)->toDateString()
                : now($timezone)->toDateString();
        } catch (Throwable) {
            $this->error('The --date option must be a valid date such as 2026-11-02.');
            return self::FAILURE;
        }

        $preview = (bool) $this->option('preview');
        $manual = (bool) $this->option('manual');
        $recipientOverride = trim((string) $this->option('to'));
        $recipients = $recipientOverride !== ''
            ? array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $recipientOverride) ?: [])))
            : config('social_reports.recipients', []);
        Log::info('Daily social performance report generation started.', ['report_date' => $reportDate, 'preview' => $preview, 'manual' => $manual]);

        if (! $preview && ! $manual) {
            if (! config('social_reports.enabled')) {
                $this->warn('Daily email reporting is disabled. Set MANAGER_REPORT_ENABLED=true after reviewing a preview.');
                Log::notice('Daily social performance email skipped because reporting is disabled.', ['report_date' => $reportDate]);
                return self::SUCCESS;
            }
            if ($recipients === []) {
                $this->error('No manager email recipient is configured in MANAGER_REPORT_EMAIL.');
                Log::error('Daily social performance email cannot run without configured recipients.', ['report_date' => $reportDate]);
                return self::FAILURE;
            }
            $claim = $this->claimReportDate($reportDate);
            if ($claim === 'sent') {
                $this->info("A report for {$reportDate} has already been sent; skipping duplicate.");
                return self::SUCCESS;
            }
            if ($claim === 'busy') {
                $this->warn("A report for {$reportDate} is already being generated.");
                return self::SUCCESS;
            }
        }
        if (! $preview && $recipients === []) {
            $this->error('No recipient emails are configured. Add MANAGER_REPORT_EMAIL or pass --to for a manual send.');
            return self::FAILURE;
        }

        try {
            $report = $this->buildReport($analytics, $summaryService, $instagram, $reportDate, $timezone, $manual);
            Log::info('Daily social performance report data generated.', [
                'report_date' => $reportDate,
                'period' => $report['period_label'],
                'account_rows' => count($report['rows']),
                'accounts_with_unavailable_metrics' => collect($report['rows'])->where('account_error', true)->count(),
            ]);
            if ($preview) {
                $this->writePreview($report);
                return self::SUCCESS;
            }
            Log::info('Submitting daily social performance report to mail transport.', [
                'report_date' => $reportDate,
                'recipient_count' => count($recipients),
                'recipients' => $recipients,
                'mailer' => config('mail.default'),
            ]);
            Mail::to($recipients)->send(new DailySocialPerformanceReport($report));
            if (! $manual) DailySocialReport::whereDate('report_date', $reportDate)->update(['status' => 'sent', 'sent_at' => now(), 'failure_message' => null]);
            Log::info('Daily social performance report accepted by the configured mail transport.', ['report_date' => $reportDate, 'manual' => $manual, 'recipients' => $recipients, 'account_rows' => count($report['rows']), 'mailer' => config('mail.default')]);
            $this->info("Consolidated report for {$reportDate} accepted by the configured mail transport for ".count($recipients).' recipient(s).');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (! $preview && ! $manual) {
                DailySocialReport::whereDate('report_date', $reportDate)->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 4000)]);
            }
            Log::error('Daily social performance report failed.', ['report_date' => $reportDate, 'message' => $exception->getMessage(), 'exception' => $exception]);
            $this->error('Report generation failed: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function claimReportDate(string $date): string
    {
        return DB::transaction(function () use ($date): string {
            $inserted = DB::table('daily_social_reports')->insertOrIgnore([
                'report_date' => $date, 'status' => 'generating', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $report = DailySocialReport::whereDate('report_date', $date)->lockForUpdate()->first();
            if ($inserted === 1) return 'claimed';
            if ($report->status === 'sent') return 'sent';
            if ($report->status === 'generating' && $report->updated_at?->gt(now()->subMinutes(30))) return 'busy';
            $report->update(['status' => 'generating', 'failure_message' => null]);
            return 'claimed';
        });
    }

    private function buildReport(MetaAnalyticsService $analytics, AnalyticsReportSummaryService $summaryService, InstagramLoginService $instagram, string $reportDate, string $timezone, bool $manual): array
    {
        $date = Carbon::parse($reportDate, $timezone)->startOfDay();
        // The detailed daily columns use the last completed day for scheduled
        // runs; the seven-day window always ends on reportDate, including the
        // current day at the 23:55 send time.
        $day = $manual ? $date : $date->copy()->subDay();
        $dayLabel = $manual ? 'today' : 'yesterday';
        $dayDate = $day->toDateString();
        $postsUntil = $date->toDateString();
        $postsSince = $date->copy()->subDays(6)->toDateString();
        // Include today's available content and metric points in the seven-day
        // totals for both manual and scheduled sends.
        $since = $postsSince;
        $until = $postsUntil;
        $rows = [];

        $pages = SocialPage::query()->with(['account.project'])
            ->whereIn('provider', ['facebook', 'instagram'])->where('status', 'active')
            ->whereHas('account', fn ($query) => $query->where('status', 'active')->whereHas('project'))
            ->orderBy('social_account_id')->orderBy('page_name')->get();

        foreach ($pages as $page) {
            if ($page->provider === 'instagram') {
                $row = $this->emptyRow($page, 'instagram');
                try {
                    $data = $instagram->dashboardForDailyReport($page, $since, $until);
                    $row = $this->applyAnalytics($row, $data, $analytics, $page, 'instagram', $since, $until, $postsSince, $postsUntil, $dayDate, $timezone);
                    try {
                        // Instagram returns views as a single total for a multi-day
                        // request. Fetch the report day separately so its value is
                        // not incorrectly shown as zero beside the seven-day total.
                        $dailyData = $instagram->dashboardForDailyReport($page, $dayDate, $dayDate, false);
                        $dailyMetrics = collect($dailyData['insights'] ?? [])->keyBy('name');
                        $row['yesterday_views'] = $this->dailyMetric($dailyMetrics->get('views'), $dayDate, $timezone);
                        $row['yesterday_unique_views'] = $this->dailyMetric($dailyMetrics->get('reach'), $dayDate, $timezone);
                        $data['insights_unavailable'] = array_values(array_unique(array_merge($data['insights_unavailable'] ?? [], $dailyData['insights_unavailable'] ?? [])));
                    } catch (Throwable $exception) {
                        $data['insights_unavailable'] = array_values(array_unique(array_merge($data['insights_unavailable'] ?? [], ['single_day_lookup'])));
                        Log::notice('Instagram single-day insight lookup failed for daily report.', [
                            'social_page_id' => $page->id,
                            'report_day' => $dayDate,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                    $row['metric_availability'] = (! empty($data['insights_unavailable']) || ! empty($data['media_unavailable']) || ! empty($data['media_insights_unavailable'])) ? 'Partial data' : 'Available';
                } catch (Throwable $exception) {
                    $row['account_error'] = true;
                    $row['metric_availability'] = 'Unavailable';
                    Log::warning('Standalone Instagram account metrics could not be fetched for daily report.', ['social_page_id' => $page->id, 'report_date' => $reportDate, 'message' => $exception->getMessage()]);
                }
                $rows[] = $row;
                continue;
            }
            foreach (['facebook', 'instagram'] as $platform) {
                if ($platform === 'instagram' && ! $page->instagram_business_id) continue;
                $row = $this->emptyRow($page, $platform);
                try {
                    $data = $analytics->dashboardForDailyReport($page, $platform, $since, $until);
                    $row = $this->applyAnalytics($row, $data, $analytics, $page, $platform, $since, $until, $postsSince, $postsUntil, $dayDate, $timezone);
                    $row['metric_availability'] = ! empty($data['insights_unavailable']) || ($platform === 'instagram' && (! empty($data['media_unavailable']) || ! empty($data['media_insights_unavailable']))) ? 'Partial data' : 'Available';
                } catch (Throwable $exception) {
                    $row['account_error'] = true;
                    $row['metric_availability'] = 'Unavailable';
                    Log::warning('An account was omitted from daily social metrics because analytics could not be fetched.', [
                        'social_page_id' => $page->id, 'platform' => $platform, 'report_date' => $reportDate,
                        'message' => $exception->getMessage(),
                    ]);
                }
                $rows[] = $row;
            }
        }

        $columns = array_map(fn ($column) => str_replace('Yesterday', ucfirst($dayLabel), $column), self::COLUMNS);
        $report = [
            'report_date' => $reportDate,
            'generated_at' => now($timezone)->format('M j, Y g:i A T'),
            'period_label' => Carbon::parse($postsSince, $timezone)->format('M j, Y').' to '.Carbon::parse($postsUntil, $timezone)->format('M j, Y').' (last 7 calendar days, including today; '.$dayLabel.': '.$day->format('M j, Y').')',
            'rows' => $rows,
            'columns' => $columns,
            'column_keys' => self::COLUMN_KEYS,
            'highlights' => $this->highlights($rows, $dayLabel),
        ];
        $report['summary'] = $summaryService->generate($columns, self::COLUMN_KEYS, $rows, $report['period_label']);

        return $report;
    }

    private function emptyRow(SocialPage $page, string $platform): array
    {
        return [
            'project' => $page->account?->project?->name ?? 'Unknown project',
            'account_type' => ucfirst($platform),
            'account_name' => $platform === 'instagram' ? '@'.($page->instagram_username ?: $page->page_name) : ($page->page_name ?: 'Unknown account'),
            'metric_availability' => 'Unavailable',
            'followers' => null, 'yesterday_net_change' => null, 'yesterday_follows' => null, 'yesterday_unfollows' => null,
            'total_posts' => null, 'yesterday_posts' => null, 'seven_day_posts' => null,
            'seven_day_views' => null, 'yesterday_views' => null, 'seven_day_unique_views' => null, 'yesterday_unique_views' => null,
            'seven_day_likes' => null, 'yesterday_likes' => null, 'seven_day_comments' => null, 'yesterday_comments' => null,
            'seven_day_shares' => null, 'yesterday_shares' => null, 'seven_day_saves' => null, 'yesterday_saves' => null,
            'account_error' => false,
        ];
    }

    private function applyAnalytics(array $row, array $data, MetaAnalyticsService $analytics, SocialPage $page, string $platform, string $since, string $until, string $postsSince, string $postsUntil, string $day, string $timezone): array
    {
        $followers = data_get($data, 'account.followers_count') ?? data_get($data, 'account.fan_count');
        $row['followers'] = is_numeric($followers) ? ReportMetric::value($followers) : null;
        $metricMap = collect($data['insights'] ?? [])->keyBy('name');
        $follows = $this->dailyMetric($metricMap->get('page_daily_follows'), $day, $timezone);
        $unfollows = $this->dailyMetric($metricMap->get('page_daily_unfollows'), $day, $timezone);
        if ($platform === 'facebook') {
            $row['yesterday_follows'] = $follows;
            $row['yesterday_unfollows'] = $unfollows;
            if (is_numeric($follows) && is_numeric($unfollows)) $row['yesterday_net_change'] = (float) $follows - (float) $unfollows;
        }

        $viewsName = $platform === 'instagram' ? 'views' : 'page_media_view';
        $uniqueName = $platform === 'instagram' ? 'reach' : 'page_total_media_view_unique';
        $mediaUnavailable = $platform === 'instagram' && ! empty($data['media_unavailable']);
        $items = collect($data['media'] ?? []);
        $todayItems = $items->filter(fn ($item) => $this->publishedDate($item, $timezone) === $day);
        $periodItems = $items->filter(function ($item) use ($postsSince, $postsUntil, $timezone): bool {
            $publishedDate = $this->publishedDate($item, $timezone);
            return $publishedDate !== null && $publishedDate >= $postsSince && $publishedDate <= $postsUntil;
        });
        $sumPostMetric = function ($posts, string $name) use ($platform): int|float|null {
            if ($platform === 'instagram' && $posts->isNotEmpty() && $posts->contains(fn ($item) => ! is_numeric(data_get($item, 'metrics.'.$name)))) return null;
            return $posts->sum(fn ($item) => is_numeric(data_get($item, 'metrics.'.$name)) ? (float) data_get($item, 'metrics.'.$name) : 0);
        };
        // Views and unique viewers are page/account daily insights, not lifetime
        // views on content that happened to be published on that same date.
        $row['seven_day_views'] = $this->sumMetric($metricMap->get($viewsName), $postsSince, $postsUntil, $timezone);
        $row['yesterday_views'] = $this->dailyMetric($metricMap->get($viewsName), $day, $timezone);
        $row['yesterday_unique_views'] = $this->dailyMetric($metricMap->get($uniqueName), $day, $timezone);
        // Daily unique audiences overlap across days. A seven-day unique total
        // is not available from these daily metrics, so leave it unavailable.
        $likesName = $platform === 'instagram' ? 'likes' : 'reactions';
        $row['yesterday_likes'] = $mediaUnavailable ? null : $sumPostMetric($todayItems, $likesName);
        $row['seven_day_likes'] = $mediaUnavailable ? null : $sumPostMetric($periodItems, $likesName);
        $row['yesterday_comments'] = $mediaUnavailable ? null : $sumPostMetric($todayItems, 'comments');
        $row['seven_day_comments'] = $mediaUnavailable ? null : $sumPostMetric($periodItems, 'comments');
        $row['yesterday_shares'] = $mediaUnavailable ? null : $sumPostMetric($todayItems, 'shares');
        $row['seven_day_shares'] = $mediaUnavailable ? null : $sumPostMetric($periodItems, 'shares');
        if ($platform === 'instagram') {
            $row['yesterday_saves'] = $mediaUnavailable ? null : $sumPostMetric($todayItems, 'saved');
            $row['seven_day_saves'] = $mediaUnavailable ? null : $sumPostMetric($periodItems, 'saved');
        }
        if ($page->provider === 'instagram') {
            $postCounts = [
                'total' => data_get($data, 'account.media_count'),
                'day' => $mediaUnavailable ? null : $todayItems->count(),
                'period' => $mediaUnavailable ? null : $periodItems->count(),
            ];
        } else {
            $postCounts = $analytics->publishedPostCounts(
                $page,
                $platform,
                $postsSince,
                $postsUntil,
                $day,
                $timezone,
                data_get($data, 'account.media_count'),
            );
        }
        $preserveUnavailable = fn ($value) => $platform === 'instagram' && ! is_numeric($value) ? null : ReportMetric::value($value);
        $row['total_posts'] = $preserveUnavailable($postCounts['total'] ?? null);
        $row['yesterday_posts'] = $preserveUnavailable($postCounts['day'] ?? null);
        $row['seven_day_posts'] = $preserveUnavailable($postCounts['period'] ?? null);
        Log::info('Daily report published-post counts calculated.', [
            'project_id' => $page->account?->project_id,
            'social_account_id' => $page->social_account_id,
            'social_page_id' => $page->id,
            'platform' => $platform,
            'total_published_posts' => $row['total_posts'],
            'day_published_posts' => $row['yesterday_posts'],
            'seven_day_published_posts' => $row['seven_day_posts'],
            'day' => $day,
            'seven_day_since' => $postsSince,
            'seven_day_until' => $postsUntil,
        ]);
        return $row;
    }

    private function insightValues(?array $metric): array
    {
        if (! $metric) return [];
        if (isset($metric['values']) && is_array($metric['values'])) return $metric['values'];
        if (array_key_exists('total_value', $metric)) return [['value' => data_get($metric, 'total_value.value'), 'end_time' => $metric['end_time'] ?? null]];
        return [];
    }

    private function dailyMetric(?array $metric, string $date, string $timezone): int|float|null
    {
        if ($metric['_analytics_total_only'] ?? false) return null;
        foreach ($this->insightValues($metric) as $point) {
            if (empty($point['end_time'])) continue;
            if (Carbon::parse($point['end_time'])->setTimezone($timezone)->toDateString() === $date && is_numeric($point['value'] ?? null)) return $point['value'] + 0;
        }
        return null;
    }

    private function sumMetric(?array $metric, string $since, string $until, string $timezone): int|float|null
    {
        $values = [];
        foreach ($this->insightValues($metric) as $point) {
            if (empty($point['end_time']) || ! is_numeric($point['value'] ?? null)) continue;
            $date = Carbon::parse($point['end_time'])->setTimezone($timezone)->toDateString();
            if ($date >= $since && $date <= $until) $values[] = (float) $point['value'];
        }
        return $values === [] ? null : array_sum($values);
    }

    private function publishedDate(array $item, string $timezone): ?string
    {
        $timestamp = $item['published_at'] ?? null;
        return $timestamp ? Carbon::parse($timestamp)->setTimezone($timezone)->toDateString() : null;
    }

    private function highlights(array $rows, string $dayLabel): array
    {
        $highlights = [];
        $growthRows = collect($rows)->filter(fn ($row) => is_numeric($row['yesterday_net_change'] ?? null));
        if ($growthRows->isNotEmpty()) {
            $top = $growthRows->sortByDesc('yesterday_net_change')->first();
            if ($top['yesterday_net_change'] > 0) $highlights[] = $top['account_name'].' had the highest follower growth: +'.number_format($top['yesterday_net_change']).'.';
            $decliners = $growthRows->filter(fn ($row) => $row['yesterday_net_change'] < 0);
            foreach ($decliners as $row) $highlights[] = $row['account_name'].' had negative follower growth: '.number_format($row['yesterday_net_change']).'.';
        }
        $viewRows = collect($rows)->filter(fn ($row) => is_numeric($row['yesterday_views'] ?? null));
        if ($viewRows->isNotEmpty()) {
            $top = $viewRows->sortByDesc('yesterday_views')->first();
            $highlights[] = $top['account_name'].' had the highest views '.$dayLabel.': '.number_format($top['yesterday_views']).'.';
        }
        $published = collect($rows)->filter(fn ($row) => is_numeric($row['yesterday_posts'] ?? null));
        if ($published->isNotEmpty()) $highlights[] = number_format($published->sum('yesterday_posts')).' posts were published '.$dayLabel.' across accounts with available data.';
        return $highlights;
    }

    private function writePreview(array $report): void
    {
        $mailable = new DailySocialPerformanceReport($report);
        $csv = $mailable->csvContent();
        $path = 'reports/previews/daily-social-performance-'.$report['report_date'].'.csv';
        Storage::disk('local')->put($path, $csv);
        $this->info('Preview generated without sending email.');
        $this->line('CSV: '.storage_path('app/'.$path));
        $this->line('Account rows: '.count($report['rows']));
        foreach ($report['rows'] as $row) $this->line($row['project'].' | '.$row['account_type'].' | '.$row['account_name'].' | Followers: '.ReportMetric::display($row['followers']).' | Net change: '.ReportMetric::display($row['yesterday_net_change']).' | Views: '.ReportMetric::display($row['yesterday_views']));
        if ($report['highlights']) $this->line('Highlights: '.implode(' ', $report['highlights']));
        Log::info('Daily social performance preview generated.', ['report_date' => $report['report_date'], 'csv_path' => $path, 'account_rows' => count($report['rows'])]);
    }
}
