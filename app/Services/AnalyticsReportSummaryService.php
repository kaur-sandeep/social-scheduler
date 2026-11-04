<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AnalyticsReportSummaryService
{
    /** Generate a short natural-language summary, falling through on quota/API errors. */
    public function generate(array $columns, array $columnKeys, array $rows, string $period): string
    {
        $table = array_map(function (array $row) use ($columns, $columnKeys): array {
            $values = [];
            foreach ($columnKeys as $index => $key) {
                $values[$columns[$index] ?? $key] = $row[$key] ?? 0;
            }
            return $values;
        }, $rows);

        $system = 'Write a concise, natural-sounding human report summary using only the supplied analytics table. Write 2 to 4 sentences in plain text, with no heading, bullets, markdown, or claim that you are an AI. Mention the reporting period, useful high or low results, and meaningful changes only when the table supports them. Do not invent causes, goals, recommendations, or missing values. If the table is empty, say no account data was available.';
        $user = json_encode(['period' => $period, 'accounts' => $table], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach (['anthropic', 'gemini', 'groq', 'openrouter'] as $provider) {
            $key = config('services.'.$provider.'.api_key');
            if (! is_string($key) || trim($key) === '') continue;

            try {
                $summary = match ($provider) {
                    'anthropic' => $this->anthropic($key, $system, $user),
                    'gemini' => $this->gemini($key, $system, $user),
                    'groq' => $this->compatibleChat('https://api.groq.com/openai/v1/chat/completions', $key, config('services.groq.model'), $system, $user),
                    'openrouter' => $this->compatibleChat('https://openrouter.ai/api/v1/chat/completions', $key, config('services.openrouter.model'), $system, $user, true),
                };

                if (is_string($summary) && trim($summary) !== '') {
                    return trim($summary);
                }
                throw new RuntimeException('Provider returned an empty summary.');
            } catch (Throwable $exception) {
                // Continue to the next configured provider, including on HTTP 429.
                $message = $exception->getMessage();
                Log::notice('Analytics email summary provider failed; trying the next provider.', [
                    'provider' => $provider,
                    'reason' => preg_match('/^HTTP \d{3}$/', $message) ? $message : 'request_failed',
                ]);
            }
        }

        return $this->fallback($table, $period);
    }

    private function anthropic(string $key, string $system, string $user): string
    {
        $response = Http::timeout(30)->withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-sonnet-4-0'),
            'max_tokens' => 400,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ]);

        if ($response->failed()) throw new RuntimeException('HTTP '.$response->status());
        return (string) $response->json('content.0.text', '');
    }

    private function gemini(string $key, string $system, string $user): string
    {
        $model = config('services.gemini.model', 'gemini-3.5-flash-lite');
        $response = Http::timeout(30)->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.urlencode($key), [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => ['maxOutputTokens' => 400, 'temperature' => 0.4],
        ]);

        if ($response->failed()) throw new RuntimeException('HTTP '.$response->status());
        return (string) $response->json('candidates.0.content.parts.0.text', '');
    }

    private function compatibleChat(string $url, string $key, ?string $model, string $system, string $user, bool $openRouter = false): string
    {
        $request = Http::timeout(30)->withToken($key);
        if ($openRouter) {
            $request = $request->withHeaders([
                'HTTP-Referer' => config('app.url', 'https://social.cogniter.com'),
                'X-Title' => config('app.name', 'Social Scheduler'),
            ]);
        }

        $response = $request->post($url, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        if ($response->failed()) throw new RuntimeException('HTTP '.$response->status());
        return (string) $response->json('choices.0.message.content', '');
    }

    private function fallback(array $table, string $period): string
    {
        if ($table === []) return 'No account data was available for '.$period.'.';

        $accounts = count($table);
        $todayColumn = array_key_exists('Today Published Posts', $table[0]) ? 'Today Published Posts' : 'Yesterday Published Posts';
        $todayPosts = array_sum(array_map(fn (array $row) => is_numeric($row[$todayColumn] ?? null) ? (float) $row[$todayColumn] : 0, $table));
        $weekPosts = array_sum(array_map(fn (array $row) => is_numeric($row['Last 7 Days Published Posts'] ?? null) ? (float) $row['Last 7 Days Published Posts'] : 0, $table));
        return "The report covers {$accounts} connected account(s) for {$period}. {$this->formatNumber($todayPosts)} posts were published on the reporting day, and {$this->formatNumber($weekPosts)} were published during the last seven days.";
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, $value === floor($value) ? 0 : 1);
    }
}
