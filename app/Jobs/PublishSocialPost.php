<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\FailedPost;
use App\Models\Post;
use App\Services\NotificationService;
use App\Services\Social\Clients\SocialApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

abstract class PublishSocialPost implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    abstract protected function service(): string;
    abstract protected function provider(): string;
    public function __construct(public Post $post) {}
    public function handle(NotificationService $notifications): void
    {
        $this->post->update(['status' => PostStatus::Publishing]);
        try { $response = app($this->service())->publish($this->post->fresh(['socialPage', 'media', 'user'])); }
        catch (SocialApiException $exception) {
            if ($exception->isAuthenticationFailure()) {
                $this->fail($exception);

                return;
            }

            $this->post->update([
                'status' => PostStatus::Retrying,
                'error_message' => $exception->getMessage(),
                'retry_count' => $this->attempts(),
            ]);

            throw $exception;
        }
        $this->post->update(['status' => PostStatus::Published, 'published_at' => now(), 'provider_post_id' => $response['id'] ?? data_get($response, 'data.id') ?? data_get($response, 'publish_id'), 'error_message' => null]);
        $notifications->postPublished($this->post->fresh('user'));
    }
    public function failed(Throwable $exception): void
    {
        $post = $this->post->fresh('user'); if (! $post) return;
        $retries = min($this->tries, $post->retry_count + 1);
        $post->update(['status' => PostStatus::Failed, 'error_message' => $exception->getMessage(), 'retry_count' => $retries]);
        FailedPost::create(['post_id' => $post->id, 'platform' => $this->provider(), 'error_message' => $exception->getMessage(), 'context' => ['job' => static::class, 'status_code' => $exception instanceof SocialApiException ? $exception->statusCode : null], 'retry_count' => $retries, 'next_retry_at' => $retries < $this->tries && ! ($exception instanceof SocialApiException && $exception->isAuthenticationFailure()) ? now()->addMinutes(15) : null]);
        app(NotificationService::class)->postFailed($post, $exception->getMessage());
    }
}
