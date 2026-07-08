<?php

namespace App\Console\Commands;

use App\Services\SchedulerService;
use Illuminate\Console\Command;

class DispatchScheduledPosts extends Command
{
    protected $signature = 'posts:dispatch-due';

    protected $description = 'Dispatch due scheduled social posts to the queue.';

    public function handle(SchedulerService $scheduler): int
    {
        $count = $scheduler->dispatchDuePosts();
        $this->info("Dispatched {$count} due posts.");

        return self::SUCCESS;
    }
}
