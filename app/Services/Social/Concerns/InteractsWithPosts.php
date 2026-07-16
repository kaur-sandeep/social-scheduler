<?php

namespace App\Services\Social\Concerns;

use App\Models\Post;
use App\Models\SocialPage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

trait InteractsWithPosts
{
    protected function page(Post $post, string $provider): SocialPage
    {
        $page = $post->socialPage;
        if (! $page || $page->provider !== $provider || $page->status !== 'active') {
            throw new RuntimeException("An active {$provider} destination is required.");
        }
        return $page;
    }

    protected function mediaUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return Str::startsWith($url, ['http://', 'https://'])
            ? $url
            : rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
