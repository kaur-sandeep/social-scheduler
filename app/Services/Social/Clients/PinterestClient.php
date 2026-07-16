<?php

namespace App\Services\Social\Clients;

use App\Models\Post;

class PinterestClient extends LoggedHttpClient
{
    public function createPin(Post $post, string $token, string $boardId, array $payload): array
    {
        return $this->request($post, 'POST', config('pinterest.api_url').'/pins', array_merge($payload, ['board_id' => $boardId]), ['Authorization' => "Bearer {$token}"]);
    }
}
