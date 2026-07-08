<?php

namespace App\Services;

use App\Models\Post;
use App\Notifications\PostFailedNotification;
use App\Notifications\PostPublishedNotification;

class NotificationService
{
    public function postPublished(Post $post): void
    {
        $post->user?->notify(new PostPublishedNotification($post));
    }

    public function postFailed(Post $post, string $message): void
    {
        $post->user?->notify(new PostFailedNotification($post, $message));
    }
}
