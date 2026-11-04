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
Schedule::command('report:send-daily-social-summary')
    ->dailyAt('23:55')
    ->timezone(config('social_reports.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
