<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishInstagramPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post)
    {
    }
}
