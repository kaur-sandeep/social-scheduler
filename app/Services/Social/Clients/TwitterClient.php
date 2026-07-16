<?php

namespace App\Services\Social\Clients;

use App\Models\Post;

class TwitterClient extends LoggedHttpClient
{
    public function createPost(Post $post, string $token, array $payload): array
    {
        return $this->request($post, 'POST', config('twitter.api_url').'/2/tweets', $payload, ['Authorization' => "Bearer {$token}"]);
    }
}
