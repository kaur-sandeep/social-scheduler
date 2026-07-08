<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\FailedPost;
use App\Services\SchedulerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedPosts implements ShouldQueue
{
    use Queueable;

    public function handle(SchedulerService $scheduler): void
    {
        FailedPost::query()
            ->with('post')
            ->where('resolved', false)
            ->where('retry_count', '<', 3)
            ->where('next_retry_at', '<=', now())
            ->limit(100)
            ->get()
            ->each(function (FailedPost $failedPost) use ($scheduler) {
                $failedPost->post->update([
                    'status' => PostStatus::Queued,
                    'error_message' => null,
                ]);

                $failedPost->update(['resolved' => true]);
                $scheduler->dispatch($failedPost->post);
            });
    }
}
