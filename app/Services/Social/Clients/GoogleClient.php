<?php

namespace App\Services\Social\Clients;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GoogleClient extends LoggedHttpClient
{
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->tokenRequest(['code' => $code, 'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code']);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->tokenRequest(['refresh_token' => $refreshToken, 'grant_type' => 'refresh_token']);
    }

    public function channel(string $token): array
    {
        $response = Http::acceptJson()->withToken($token)->get(config('google.youtube_api_url').'/channels', ['part' => 'snippet', 'mine' => 'true']);
        if ($response->failed()) throw new SocialApiException($this->errorMessage($response->json() ?? []), $response->status());
        $channel = data_get($response->json(), 'items.0');
        if (! is_array($channel)) throw new SocialApiException('No YouTube channel is associated with this Google account.');
        return $channel;
    }

    public function uploadVideo(Post $post, string $token, string $path, array $metadata): array
    {
        $absolutePath = Storage::disk('public')->path($path);
        if (! is_file($absolutePath)) throw new SocialApiException('Video file is unavailable.');

        $size = filesize($absolutePath);
        $mime = mime_content_type($absolutePath) ?: 'video/mp4';
        $startedAt = microtime(true);
        $session = Http::acceptJson()->withToken($token)->withHeaders([
            'X-Upload-Content-Length' => (string) $size,
            'X-Upload-Content-Type' => $mime,
        ])->post(config('google.youtube_upload_url').'?uploadType=resumable&part=snippet,status', $metadata);
        $uploadUrl = $session->header('Location');
        if (! $session->successful() || ! $uploadUrl) {
            $this->log($post, config('google.youtube_upload_url'), ['title' => data_get($metadata, 'snippet.title')], $session->json() ?? ['body' => $session->body()], $session->status(), $startedAt);
            throw new SocialApiException($this->errorMessage($session->json() ?? []), $session->status());
        }

        $handle = fopen($absolutePath, 'r');
        try {
            $response = Http::acceptJson()->withToken($token)->withHeaders([
                'Content-Length' => (string) $size,
                'Content-Type' => $mime,
            ])->send('PUT', $uploadUrl, ['body' => $handle]);
        } finally {
            fclose($handle);
        }

        $body = $response->json() ?? ['body' => $response->body()];
        $this->log($post, config('google.youtube_upload_url'), ['title' => data_get($metadata, 'snippet.title'), 'file_size' => $size], $body, $response->status(), $startedAt);
        if ($response->failed()) throw new SocialApiException($this->errorMessage($body), $response->status());
        return $body;
    }

    private function tokenRequest(array $payload): array
    {
        $response = Http::asForm()->acceptJson()->post('https://oauth2.googleapis.com/token', $payload + ['client_id' => config('google.client_id'), 'client_secret' => config('google.client_secret')]);
        if ($response->failed()) throw new SocialApiException($this->errorMessage($response->json() ?? []), $response->status());
        return $response->json();
    }

    private function log(Post $post, string $endpoint, array $request, array $response, int $status, float $startedAt): void
    {
        $post->logs()->create(['platform' => 'youtube', 'endpoint' => $endpoint, 'api_request' => $request, 'api_response' => $response, 'status_code' => $status, 'execution_time_ms' => (int) ((microtime(true) - $startedAt) * 1000), 'success' => $status >= 200 && $status < 300, 'failure_reason' => $status >= 200 && $status < 300 ? null : $this->errorMessage($response)]);
    }
}
