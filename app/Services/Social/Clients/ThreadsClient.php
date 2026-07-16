<?php

namespace App\Services\Social\Clients;

use App\Models\Post;

class ThreadsClient extends LoggedHttpClient
{
    public function createContainer(Post $post, string $userId, string $token, array $payload): array
    {
        $payload['access_token'] = $token;
        return $this->request($post, 'POST', config('threads.api_url')."/{$userId}/threads", $payload, [], true);
    }

    public function publishContainer(Post $post, string $userId, string $token, string $creationId): array
    {
        return $this->request($post, 'POST', config('threads.api_url')."/{$userId}/threads_publish", ['creation_id' => $creationId, 'access_token' => $token], [], true);
    }
}
