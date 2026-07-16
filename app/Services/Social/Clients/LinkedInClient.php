<?php

namespace App\Services\Social\Clients;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class LinkedInClient extends LoggedHttpClient
{
    public function createPost(Post $post, string $token, array $payload): array
    {
        return $this->request($post, 'POST', config('linkedin.api_url').'/rest/posts', $payload, ['Authorization' => "Bearer {$token}", 'Linkedin-Version' => config('linkedin.version'), 'X-Restli-Protocol-Version' => '2.0.0']);
    }

    public function uploadImage(Post $post, string $token, string $owner, string $path, string $mimeType): string
    {
        $upload = $this->request($post, 'POST', config('linkedin.api_url').'/rest/images?action=initializeUpload', ['initializeUploadRequest' => ['owner' => $owner]], $this->headers($token));
        $uploadUrl = data_get($upload, 'value.uploadUrl');
        $imageUrn = data_get($upload, 'value.image');
        if (! is_string($uploadUrl) || ! is_string($imageUrn)) throw new SocialApiException('LinkedIn did not return an image upload URL.');

        $absolutePath = Storage::disk('public')->path($path);
        if (! is_file($absolutePath)) throw new SocialApiException('Image file is unavailable.');

        $startedAt = microtime(true);
        $handle = fopen($absolutePath, 'r');
        try {
            $response = Http::timeout(120)->withToken($token)->withHeaders(['Content-Type' => $mimeType, 'Content-Length' => (string) filesize($absolutePath)])->send('PUT', $uploadUrl, ['body' => $handle]);
        } finally {
            fclose($handle);
        }

        $body = $response->json() ?? ['body' => $response->body()];
        $this->logUpload($post, $uploadUrl, ['path' => $path, 'mime_type' => $mimeType], $body, $response->status(), $startedAt);
        if ($response->failed()) throw new SocialApiException($this->errorMessage($body), $response->status());
        return $imageUrn;
    }

    private function headers(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Linkedin-Version' => config('linkedin.version'), 'X-Restli-Protocol-Version' => '2.0.0'];
    }

    private function logUpload(Post $post, string $url, array $request, array $response, int $status, float $startedAt): void
    {
        $post->logs()->create(['platform' => $post->platform, 'endpoint' => strtok($url, '?'), 'api_request' => $request, 'api_response' => $response, 'status_code' => $status, 'execution_time_ms' => (int) ((microtime(true) - $startedAt) * 1000), 'success' => $status >= 200 && $status < 300, 'failure_reason' => $status >= 200 && $status < 300 ? null : $this->errorMessage($response)]);
    }
}
