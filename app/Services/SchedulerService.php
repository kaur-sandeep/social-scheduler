<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Jobs\PublishFacebookPost;
use App\Jobs\PublishInstagramPost;
use App\Jobs\PublishLinkedInPost;
use App\Jobs\PublishPinterestPost;
use App\Jobs\PublishThreadsPost;
use App\Jobs\PublishTikTokPost;
use App\Jobs\PublishTwitterPost;
use App\Jobs\PublishYouTubePost;
use App\Models\Post;
use App\Repositories\PostRepository;
use Illuminate\Support\Facades\DB;

class SchedulerService
{
    public function __construct(private readonly PostRepository $posts)
    {
    }

    public function dispatchDuePosts(): int
    {
        return DB::transaction(function () {
            $count = 0;

            foreach ($this->posts->due(now()->utc()) as $post) {
                $post->update(['status' => PostStatus::Queued]);
                $this->dispatch($post);
                $count++;
            }

            return $count;
        });
    }

    public function dispatch(Post $post): void
    {
       match ($post->platform) {

            'facebook' =>
                PublishFacebookPost::dispatch($post),

            'instagram' =>
                PublishInstagramPost::dispatch($post),

            'linkedin' => PublishLinkedInPost::dispatch($post),
            'tiktok' => PublishTikTokPost::dispatch($post),
            'twitter' => PublishTwitterPost::dispatch($post),
            'pinterest' => PublishPinterestPost::dispatch($post),
            'youtube' => PublishYouTubePost::dispatch($post),
            'threads' => PublishThreadsPost::dispatch($post),

            default =>
                throw new \InvalidArgumentException(
                    "Publishing for {$post->platform} is not implemented yet."
                ),
        };
    }
}
