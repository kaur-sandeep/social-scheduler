<?php

namespace App\Console\Commands;

use App\Mail\DailySocialPerformanceReport;
use App\Models\DailySocialReport;
use App\Models\Post;
use App\Models\SocialPage;
use App\Services\Social\MetaAnalyticsService;
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
        'Project', 'Account Type', 'Account Name', 'Total Followers',
        'Yesterday Follows', 'Yesterday Unfollows', 'Total Published Posts',
        'Yesterday Published Posts', 'Last 7 Days Published Posts', 'Last 7 Days Views', 'Yesterday Views',
        'Last 7 Days Unique Views', 'Yesterday Unique Views', 'Last 7 Days Likes / Reactions',
        'Yesterday Likes / Reactions', 'Last 7 Days Comments', 'Yesterday Comments', 'Last 7 Days Shares',
        'Yesterday Shares', 'Last 7 Days Saves', 'Yesterday Saves',
    ];

    private const COLUMN_KEYS = [
        'project', 'account_type', 'account_name', 'followers', 'yesterday_follows',
        'yesterday_unfollows', 'total_posts', 'yesterday_posts', 'seven_day_posts',
        'seven_day_views', 'yesterday_views', 'seven_day_unique_views', 'yesterday_unique_views',
        'seven_day_likes', 'yesterday_likes', 'seven_day_comments', 'yesterday_comments', 'seven_day_shares',
        'yesterday_shares', 'seven_day_saves', 'yesterday_saves',
    ];

    public function handle(MetaAnalyticsService $analytics): int
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
            $report = $this->buildReport($analytics, $reportDate, $timezone, $manual);
            if ($preview) {
                $this->writePreview($report);
                return self::SUCCESS;
            }
            Mail::to($recipients)->send(new DailySocialPerformanceReport($report));
            if (! $manual) DailySocialReport::whereDate('report_date', $reportDate)->update(['status' => 'sent', 'sent_at' => now(), 'failure_message' => null]);
            Log::info('Daily social performance report email sent.', ['report_date' => $reportDate, 'manual' => $manual, 'recipients' => $recipients, 'account_rows' => count($report['rows'])]);
            $this->info("Consolidated report for {$reportDate} sent to ".count($recipients).' recipient(s).');
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

    private function buildReport(MetaAnalyticsService $analytics, string $reportDate, string $timezone, bool $manual): array
    {
        $date = Carbon::parse($reportDate, $timezone)->startOfDay();
        // Manual sends report the current day's progress; scheduled reports sent
        // in the morning report yesterday, when Meta's daily metrics are complete.
        $day = $manual ? $date : $date->copy()->subDay();
        $dayLabel = $manual ? 'today' : 'yesterday';
        $since = $day->copy()->subDays(6)->toDateString();
        $dayDate = $day->toDateString();
        $until = $dayDate;
        $rows = [];

        $pages = SocialPage::query()->with(['account.project'])
            ->where('provider', 'facebook')->where('status', 'active')
            ->whereHas('account', fn ($query) => $query->where('status', 'active')->whereHas('project'))
            ->orderBy('social_account_id')->orderBy('page_name')->get();

        foreach ($pages as $page) {
            foreach (['facebook', 'instagram'] as $platform) {
                if ($platform === 'instagram' && ! $page->instagram_business_id) continue;
                $row = $this->emptyRow($page, $platform);
                try {
                    $data = $analytics->dashboardForDailyReport($page, $platform, $since, $until);
                    $row = $this->applyAnalytics($row, $data, $page, $platform, $since, $until, $dayDate, $timezone);
                } catch (Throwable $exception) {
                    $row['account_error'] = true;
                    Log::warning('An account was omitted from daily social metrics because analytics could not be fetched.', [
                        'social_page_id' => $page->id, 'platform' => $platform, 'report_date' => $reportDate,
                        'message' => $exception->getMessage(),
                    ]);
                }
                $rows[] = $row;
            }
        }

        $columns = array_map(fn ($column) => str_replace('Yesterday', ucfirst($dayLabel), $column), self::COLUMNS);
        return [
            'report_date' => $reportDate,
            'generated_at' => now($timezone)->format('M j, Y g:i A T'),
            'period_label' => Carbon::parse($since)->format('M j, Y').' – '.Carbon::parse($until)->format('M j, Y').' (7 days, including '.$dayLabel.': '.$day->format('M j, Y').')',
            'rows' => $rows,
            'columns' => $columns,
            'column_keys' => self::COLUMN_KEYS,
            'highlights' => $this->highlights($rows, $dayLabel),
        ];
    }

    private function emptyRow(SocialPage $page, string $platform): array
    {
        return [
            'project' => $page->account?->project?->name ?? 'Unknown project',
            'account_type' => ucfirst($platform),
            'account_name' => $platform === 'instagram' ? '@'.($page->instagram_username ?: $page->page_name) : ($page->page_name ?: 'Unknown account'),
            'followers' => 0, 'yesterday_net_change' => 0, 'yesterday_follows' => 0, 'yesterday_unfollows' => 0,
            'total_posts' => 0, 'yesterday_posts' => 0, 'seven_day_posts' => 0,
            'seven_day_views' => 0, 'yesterday_views' => 0, 'seven_day_unique_views' => 0, 'yesterday_unique_views' => 0,
            'seven_day_likes' => 0, 'yesterday_likes' => 0, 'seven_day_comments' => 0, 'yesterday_comments' => 0,
            'seven_day_shares' => 0, 'yesterday_shares' => 0, 'seven_day_saves' => 0, 'yesterday_saves' => 0,
            'account_error' => false,
        ];
    }

    private function applyAnalytics(array $row, array $data, SocialPage $page, string $platform, string $since, string $until, string $yesterday, string $timezone): array
    {
        $row['followers'] = ReportMetric::value(data_get($data, 'account.followers_count') ?? data_get($data, 'account.fan_count'));
        $metricMap = collect($data['insights'] ?? [])->keyBy('name');
        $follows = ReportMetric::value($this->dailyMetric($metricMap->get('page_daily_follows'), $yesterday, $timezone));
        $unfollows = ReportMetric::value($this->dailyMetric($metricMap->get('page_daily_unfollows'), $yesterday, $timezone));
        if ($platform === 'facebook') {
            $row['yesterday_follows'] = $follows;
            $row['yesterday_unfollows'] = $unfollows;
            if (is_numeric($follows) && is_numeric($unfollows)) $row['yesterday_net_change'] = (float) $follows - (float) $unfollows;
        }

        $viewsName = $platform === 'instagram' ? 'views' : 'page_media_view';
        $uniqueName = $platform === 'instagram' ? 'reach' : 'page_total_media_view_unique';
        $row['seven_day_views'] = ReportMetric::value($this->sumMetric($metricMap->get($viewsName), $since, $until, $timezone));
        $row['yesterday_views'] = ReportMetric::value($this->dailyMetric($metricMap->get($viewsName), $yesterday, $timezone));
        $row['yesterday_unique_views'] = ReportMetric::value($this->dailyMetric($metricMap->get($uniqueName), $yesterday, $timezone));
        // Daily unique audiences overlap across days. There is no reliable period-unique
        // value in this endpoint response, so deliberately do not sum daily reach.
        $items = collect($data['media'] ?? []);
        // All three post counts use the same successful publishing records, account,
        // platform and publication timestamp. Meta's recent media feed is a different
        // source and can include posts that were not published through this app.
        $publishedPosts = Post::query()->where('social_page_id', $page->id)->where('platform', $platform)
            ->where('status', 'published')->whereNotNull('published_at');
        $row['total_posts'] = (clone $publishedPosts)->count();
        $dayStart = Carbon::parse($yesterday, $timezone)->startOfDay()->utc();
        $dayEnd = $dayStart->copy()->addDay();
        $row['yesterday_posts'] = (clone $publishedPosts)->where('published_at', '>=', $dayStart)->where('published_at', '<', $dayEnd)->count();
        $periodStart = Carbon::parse($since, $timezone)->startOfDay()->utc();
        $periodEnd = Carbon::parse($until, $timezone)->startOfDay()->addDay()->utc();
        $row['seven_day_posts'] = (clone $publishedPosts)->where('published_at', '>=', $periodStart)->where('published_at', '<', $periodEnd)->count();
        Log::info('Daily report published-post counts calculated.', [
            'project_id' => $page->account?->project_id,
            'social_account_id' => $page->social_account_id,
            'social_page_id' => $page->id,
            'platform' => $platform,
            'total_published_posts' => $row['total_posts'],
            'day_published_posts' => $row['yesterday_posts'],
            'seven_day_published_posts' => $row['seven_day_posts'],
            'day' => $yesterday,
            'seven_day_since' => $since,
            'seven_day_until' => $until,
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
            if ($top['yesterday_net_change'] > 0) $highlights[] = $top['account_name'].' ('.$top['project'].') had the highest follower growth: +'.number_format($top['yesterday_net_change']).'.';
            $decliners = $growthRows->filter(fn ($row) => $row['yesterday_net_change'] < 0);
            foreach ($decliners as $row) $highlights[] = $row['account_name'].' ('.$row['project'].') had negative follower growth: '.number_format($row['yesterday_net_change']).'.';
        }
        $viewRows = collect($rows)->filter(fn ($row) => is_numeric($row['yesterday_views'] ?? null));
        if ($viewRows->isNotEmpty()) {
            $top = $viewRows->sortByDesc('yesterday_views')->first();
            $highlights[] = $top['account_name'].' ('.$top['project'].') had the highest views '.$dayLabel.': '.number_format($top['yesterday_views']).'.';
        }
        $published = collect($rows)->filter(fn ($row) => is_numeric($row['yesterday_posts'] ?? null));
        if ($published->isNotEmpty()) $highlights[] = number_format($published->sum('yesterday_posts')).' posts were published '.$dayLabel.' across accounts with available data.';
        if ($rows !== [] && collect($rows)->every(fn ($row) => is_numeric($row['total_posts'] ?? null))) {
            $highlights[] = number_format(collect($rows)->sum('total_posts')).' total published posts are represented across connected accounts using Social Scheduler publishing records.';
        }
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
