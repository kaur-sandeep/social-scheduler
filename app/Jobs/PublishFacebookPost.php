<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\FailedPost;
use App\Models\Post;
use App\Services\NotificationService;
use App\Services\Social\FacebookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishFacebookPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Post $post)
    {
    }

    public function handle(FacebookService $facebook, NotificationService $notifications): void
    {
        $this->post->update(['status' => PostStatus::Publishing]);

        $response = $facebook->publish($this->post->fresh(['socialPage', 'media', 'user']));

        $this->post->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
            'provider_post_id' => $response['id'] ?? $response['post_id'] ?? null,
            'error_message' => null,
        ]);

        $notifications->postPublished($this->post->fresh('user'));
    }

    public function failed(Throwable $exception): void
    {
        $post = $this->post->fresh('user');
        $retryCount = min(3, $post->retry_count + 1);

        $post->update([
            'status' => PostStatus::Failed,
            'error_message' => $exception->getMessage(),
            'retry_count' => $retryCount,
        ]);

        FailedPost::query()->create([
            'post_id' => $post->id,
            'platform' => 'facebook',
            'error_message' => $exception->getMessage(),
            'context' => ['job' => self::class],
            'retry_count' => $retryCount,
            'next_retry_at' => $retryCount < 3 ? now()->addMinutes(15) : null,
        ]);

        app(NotificationService::class)->postFailed($post, $exception->getMessage());
    }
}
