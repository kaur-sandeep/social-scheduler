<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Social\TikTokService;
use RuntimeException;

class PublishTikTokPost extends PublishSocialPost
{
    public function __construct(public Post $post) {}

    public function handle(TikTokService $tiktok): void
    {
        $this->post->update(['status' => PostStatus::Publishing]);
        $response = $tiktok->publish($this->post->fresh(['socialPage.account', 'media', 'user']));
        $publishId = (string) data_get($response, 'data.publish_id');

        if ($publishId === '') throw new RuntimeException('TikTok did not return a publish ID.');

        $this->post->update(['provider_post_id' => $publishId, 'error_message' => null]);
        CheckTikTokPostStatus::dispatch($this->post->id)->delay(now()->addSeconds(30));
    }

    protected function service(): string { return TikTokService::class; }
    protected function provider(): string { return 'tiktok'; }
}
