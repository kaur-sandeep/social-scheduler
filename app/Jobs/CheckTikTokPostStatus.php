<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\FailedPost;
use App\Models\Post;
use App\Services\NotificationService;
use App\Services\Social\TikTokService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CheckTikTokPostStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $postId, public int $polls = 0) {}

    public function handle(TikTokService $tiktok, NotificationService $notifications): void
    {
        $post = Post::query()->with(['socialPage.account', 'user'])->find($this->postId);

        if (! $post || $post->platform !== 'tiktok' || $post->status !== PostStatus::Publishing || ! $post->provider_post_id) return;

        $result = $tiktok->status($post);
        $data = $result['data'] ?? [];
        $status = (string) ($data['status'] ?? '');

        if ($status === 'PUBLISH_COMPLETE') {
            $post->update([
                'status' => PostStatus::Published,
                'published_at' => now(),
                'provider_post_id' => data_get($data, 'publicaly_available_post_id.0') ?: $post->provider_post_id,
                'error_message' => null,
            ]);
            $notifications->postPublished($post->fresh('user'));

            return;
        }

        if ($status === 'FAILED') {
            $message = (string) ($data['fail_reason'] ?? 'TikTok could not publish this video.');
            $post->update(['status' => PostStatus::Failed, 'error_message' => $message, 'retry_count' => 1]);
            FailedPost::query()->create([
                'post_id' => $post->id, 'platform' => 'tiktok', 'error_message' => $message,
                'context' => ['job' => self::class, 'tiktok_status' => $status], 'retry_count' => 1,
            ]);
            $notifications->postFailed($post->fresh('user'), $message);

            return;
        }

        if ($this->polls >= 119) {
            $post->update(['status' => PostStatus::Failed, 'error_message' => 'TikTok publishing did not complete within one hour.']);

            return;
        }

        self::dispatch($post->id, $this->polls + 1)->delay(now()->addSeconds(30));
    }

    public function failed(Throwable $exception): void
    {
        $post = Post::query()->with('user')->find($this->postId);
        if (! $post || $post->status !== PostStatus::Publishing) return;

        $post->update(['status' => PostStatus::Failed, 'error_message' => $exception->getMessage()]);
        app(NotificationService::class)->postFailed($post->fresh('user'), $exception->getMessage());
    }
}
