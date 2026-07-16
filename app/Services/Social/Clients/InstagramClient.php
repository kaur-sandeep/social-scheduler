<?php

namespace App\Services\Social\Clients;

use App\Models\Post;

class InstagramClient extends LoggedHttpClient
{
    public function createContainer(Post $post, string $instagramId, string $token, array $payload): array
    {
        $payload['access_token'] = $token;
        return $this->request($post, 'POST', config('meta.graph_url')."/{$instagramId}/media", $payload, [], true);
    }

    public function publishContainer(Post $post, string $instagramId, string $token, string $creationId): array
    {
        return $this->request($post, 'POST', config('meta.graph_url')."/{$instagramId}/media_publish", ['creation_id' => $creationId, 'access_token' => $token], [], true);
    }

    public function containerStatus(Post $post, string $creationId, string $token): array
    {
        return $this->request($post, 'GET', config('meta.graph_url')."/{$creationId}", [
            'fields' => 'status_code,status',
            'access_token' => $token,
        ], [], true);
    }
}
