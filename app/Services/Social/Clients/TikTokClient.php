<?php

namespace App\Services\Social\Clients;

use App\Models\Post;

class TikTokClient extends LoggedHttpClient
{
    public function creatorInfo(Post $post, string $token): array
    {
        return $this->assertTikTokSuccess($this->request($post, 'POST', config('tiktok.api_url').'/v2/post/publish/creator_info/query/', [], [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json; charset=UTF-8',
        ]));
    }

    public function publish(Post $post, string $token, array $payload): array
    {
        return $this->assertTikTokSuccess($this->request($post, 'POST', config('tiktok.api_url').'/v2/post/publish/video/init/', $payload, ['Authorization' => "Bearer {$token}"]));
    }

    public function status(Post $post, string $token, string $publishId): array
    {
        return $this->assertTikTokSuccess($this->request($post, 'POST', config('tiktok.api_url').'/v2/post/publish/status/fetch/', ['publish_id' => $publishId], ['Authorization' => "Bearer {$token}"]));
    }

    private function assertTikTokSuccess(array $response): array
    {
        if (($response['error']['code'] ?? 'ok') !== 'ok') throw new SocialApiException((string) ($response['error']['message'] ?? 'TikTok API request failed.'), (int) ($response['error']['http_status_code'] ?? 422));

        return $response;
    }
}
