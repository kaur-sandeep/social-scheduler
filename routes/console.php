<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RetryFailedPosts;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posts:dispatch-due')->everyMinute()->withoutOverlapping();
Schedule::job(new RetryFailedPosts)->everyFiveMinutes()->withoutOverlapping();
// The command uses the unique daily_social_reports.report_date row as its
// idempotency guard. Avoid scheduler cache mutexes here: the report must not
// silently disappear when a shared cache lock is stale/unavailable.
Schedule::command('report:send-daily-social-summary')
    ->dailyAt('23:55')
    ->timezone(config('social_reports.timezone'));
