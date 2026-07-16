<?php

namespace App\Services\Social\Clients;

use App\Models\Post;

class LinkedInClient extends LoggedHttpClient
{
    public function createPost(Post $post, string $token, array $payload): array
    {
        return $this->request($post, 'POST', config('linkedin.api_url').'/rest/posts', $payload, ['Authorization' => "Bearer {$token}", 'Linkedin-Version' => config('linkedin.version'), 'X-Restli-Protocol-Version' => '2.0.0']);
    }
}
