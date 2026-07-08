<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostFailedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Post $post, private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'platform' => $this->post->platform,
            'message' => 'Post publishing failed.',
            'reason' => $this->reason,
        ];
    }
}
