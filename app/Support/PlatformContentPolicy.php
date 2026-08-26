<?php

namespace App\Support;

use Carbon\Carbon;

class PlatformContentPolicy
{
    public static function characterLimit(string $platform): int
    {
        return match (self::normalizePlatform($platform)) {
            'instagram' => 2200,
            'linkedin' => 3000,
            'twitter' => 280,
            // Keep Facebook's existing supported allowance; the AI prompt asks for
            // concise, business-readable copy without restricting manual posts.
            'facebook' => 63206,
            default => 63206,
        };
    }

    public static function generationRules(): string
    {
        return <<<'RULES'
Platform-specific content rules (apply the rule for every post's platform):
- Instagram: write an engaging, visual caption under 2,200 characters. Add 3–10 relevant, non-duplicated hashtags naturally; do not use more than 30 hashtags.
- Facebook: write a professional, readable business post, aiming for fewer than 1,500 characters. Hashtags are optional; use at most two and never use an Instagram-style hashtag block.
- LinkedIn: write professional B2B copy focused on business value, logistics, field operations, verification, compliance, or productivity. Use 1–3 relevant hashtags.
- X (Twitter): write one concise post of at most 280 characters, including any hashtags.
RULES;
    }

    public static function preparePost(array $post, string $userPrompt, int $index): array
    {
        $platform = self::normalizePlatform((string) ($post['platform'] ?? ''));
        $content = trim((string) ($post['content'] ?? ''));

        $content = self::normalizeHashtags($content, $platform);
        $content = self::shorten($content, self::characterLimit($platform));
        $post['content'] = $content;

        // Pending is the safe default: AI exports are always reviewed/imported before publishing.
        if (! preg_match('/\bdraft\b/i', $userPrompt)) {
            $post['status'] = 'Pending';
        }

        if (preg_match('/\btomorrow\b/i', $userPrompt)) {
            $timezone = self::requestedTimezone($userPrompt) ?? 'Asia/Kolkata';
            $post['timezone'] = $timezone;
            $post['schedule date'] = Carbon::now($timezone)->addDay()->addDays($index)->toDateString();
        }

        return $post;
    }

    public static function normalizePlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));

        return in_array($platform, ['x', 'x (twitter)'], true) ? 'twitter' : $platform;
    }

    private static function normalizeHashtags(string $content, string $platform): string
    {
        preg_match_all('/(?<!\w)#([\p{L}\p{N}_]+)/u', $content, $matches);
        $seen = [];
        $hashtags = [];
        foreach ($matches[0] as $hashtag) {
            $key = mb_strtolower($hashtag);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $hashtags[] = $hashtag;
            }
        }

        $limit = match ($platform) {
            'instagram' => 10,
            'linkedin' => 3,
            'facebook' => 2,
            'twitter' => 2,
            default => 0,
        };
        $body = trim((string) preg_replace('/(?<!\w)#[\p{L}\p{N}_]+/u', '', $content));
        $hashtags = array_slice($hashtags, 0, $limit);

        if ($platform === 'instagram' && $hashtags === []) {
            $hashtags = ['#PhotoProof', '#ProofOfDelivery', '#FieldVerification', '#GPSVerification'];
        }
        if ($platform === 'linkedin' && $hashtags === []) {
            $hashtags = ['#ProofOfDelivery', '#FieldOperations', '#Compliance'];
        }

        return trim($body.($hashtags === [] ? '' : "\n\n".implode(' ', $hashtags)));
    }

    private static function shorten(string $content, int $limit): string
    {
        if (mb_strlen($content) <= $limit) {
            return $content;
        }

        $shortened = rtrim(mb_substr($content, 0, max(1, $limit - 1)));
        $lastSpace = mb_strrpos($shortened, ' ');
        if ($lastSpace !== false && $lastSpace > $limit * 0.6) {
            $shortened = mb_substr($shortened, 0, $lastSpace);
        }

        return rtrim($shortened)."…";
    }

    private static function requestedTimezone(string $prompt): ?string
    {
        foreach (['Asia/Kolkata', 'UTC', 'America/New_York', 'Europe/London', 'Australia/Sydney'] as $timezone) {
            if (stripos($prompt, $timezone) !== false) {
                return $timezone;
            }
        }

        return null;
    }
}
