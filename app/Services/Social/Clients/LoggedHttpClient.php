<?php

namespace App\Services\Social\Clients;

use App\Models\Post;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class LoggedHttpClient
{
    protected function request(Post $post, string $method, string $url, array $payload = [], array $headers = [], bool $asForm = false): array
    {
        $startedAt = microtime(true);
        $http = Http::acceptJson()->timeout(60)->withHeaders($headers);
        $response = strtoupper($method) === 'GET'
            ? $http->get($url, $payload)
            : ($asForm
                ? $http->asForm()->send($method, $url, ['form_params' => $payload])
                : $http->send($method, $url, $payload === [] ? [] : ['json' => $payload]));
        $body = $response->json() ?? ['body' => $response->body()];

        $post->logs()->create([
            'platform' => $post->platform,
            'endpoint' => strtok($url, '?'),
            'api_request' => $this->redact($payload),
            'api_response' => $body,
            'status_code' => $response->status(),
            'execution_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'success' => $response->successful(),
            'failure_reason' => $response->successful() ? null : $this->errorMessage($body),
        ]);

        if ($response->failed()) {
            throw new SocialApiException($this->errorMessage($body), $response->status());
        }

        return $body;
    }

    protected function raw(): PendingRequest
    {
        return Http::acceptJson()->timeout(120);
    }

    protected function errorMessage(array $body): string
    {
        return (string) (data_get($body, 'error.message') ?? data_get($body, 'detail') ?? data_get($body, 'message') ?? 'Provider API request failed.');
    }

    private function redact(array $payload): array
    {
        foreach (['access_token', 'refresh_token', 'client_secret', 'Authorization'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }
}
