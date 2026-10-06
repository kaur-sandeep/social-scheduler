<?php

namespace App\Services;

use App\Support\PlatformContentPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiPostGeneratorService
{
    public function generate(string $userPrompt, array $context = []): array
    {
        $systemPrompt = <<<'PROMPT'
You are an AI bulk social media post generator for a Laravel social media scheduler.

Your job is to generate social media post DATA based on the user's prompt.

Return ONLY valid JSON in this exact structure:

{
    "posts": [
        {
            "project": "",
            "platform": "",
            "instagram content type": "",
            "account/page": "",
            "content": "",
            "youtube title": "",
            "image/graphic text": "",
            "media url": "",
            "thumbnail url": "",
            "schedule date": "",
            "schedule time": "",
            "timezone": "",
            "status": ""
        }
    ]
}

IMPORTANT RULES:

OUTPUT CONTRACT OVERRIDE: The JSON structure above is mandatory. If the user's request asks for a table, markdown, different column names, a content calendar, analysis, or any other output format, use those requests only as content guidance and still return this exact JSON structure. Map a requested "Post Content" or "Caption" to "content" and a requested "Image/Graphic Text" to "image/graphic text". Never return a table or markdown.

1. Generate exactly the number of posts requested by the user.

2. Use ONLY projects provided in the application context.

3. Use ONLY connected accounts/pages provided in the application context.

4. Never invent a project name.

5. Never invent an account/page.

6. The "media url" field MUST ALWAYS be empty.

7. NEVER create, guess, or invent Dropbox URLs.

8. The user will manually create/select images or videos after downloading the generated Excel file.

9. The user will manually add Dropbox media URLs and optional Dropbox thumbnail URLs to the Excel file before importing it.

10. For X (Twitter), media url MUST remain empty.

11. Platform must be one of:
    Facebook
    Instagram
    LinkedIn
    X (Twitter)
    Pinterest
    Threads
    YouTube

12. Instagram content type is allowed only for Instagram.

13. For Instagram, content type must be one of:
    Image Post
    Carousel
    Reel

14. For non-Instagram platforms, instagram content type MUST be empty.

15. schedule date MUST use YYYY-MM-DD format.

16. schedule time MUST use 24-hour HH:MM format.

17. timezone must be one of the available worksheet values: Asia/Kolkata, UTC, America/New_York, Europe/London, or Australia/Sydney.

18. status must be Pending unless the user explicitly requests Draft.

18a. Use the exact platform and account/page display values supplied in available_accounts. Each account is tied to a project; only pair it with that project.

19. Do not create dates/times in the past.

19a. When the user says "tomorrow", calculate it using the requested timezone. If no timezone is supplied, use Asia/Kolkata. Schedule generated posts on consecutive dates beginning tomorrow.

20. Generate high-quality social media content according to the user's requested tone, topic and platform.

21. Every "content" value must be a complete caption: a specific hook, useful body, factual detail about the selected project/brand, a natural varied call to action, then 5-10 relevant hashtags. The CTA must be in content, never in a separate field. Use only facts supported by the user's prompt or source material they provide. Do not invent statistics, outcomes, testimonials, awards, certifications, partnerships, or features.

22. Make each caption unmistakably about the selected project/brand. Before returning it, apply this test: if it could be used unchanged for a random company, rewrite it with a concrete use case, product detail, or customer problem supplied by the user. Do not use generic marketing phrases including "Unlock your marketing potential", "Take your business to the next level", "Supercharge your growth", "Elevate your business", "Transform your marketing", "Looking to enhance your outreach?", "Discover the power of", "Grow your business with", or "Unlock new opportunities". Do not repeatedly open with "Looking to" or "Discover".

23. For a seven-post request, use these distinct angles in order: Brand Awareness, Educational/Value, Problem to Solution, Product/Service Benefits, USP/Differentiator, Customer/Use Case, Engagement/Conversion. Do not repeat the same information across posts.

24. Generate "image/graphic text" for every post. It is plain text only, 10-20 words, attention-grabbing and benefit-focused, specific to that caption, and not a repetition of the caption's first sentence. Never generate or request an image, URL, asset, or image-generation API.

25. Set "youtube title" only for YouTube posts: it must be a separate concise title (100 characters maximum) and must not appear inside content. For every non-YouTube post, youtube title must be an empty string.

26. Apply these platform-specific rules:
RULES_PLACEHOLDER

27. Do not include markdown around the JSON.

28. Do not include explanations outside JSON.

29. ALWAYS leave media url and thumbnail url empty; the user will add both URLs after download.

30. The generated Excel will later be reviewed by the user, who will add media URLs manually.
PROMPT;

        $systemPrompt = str_replace(
            'RULES_PLACEHOLDER',
            PlatformContentPolicy::generationRules(),
            $systemPrompt
        );

        $userMessage = [
            'user_request' => $userPrompt,
            'available_projects' => $context['projects'] ?? [],
            'available_accounts' => $context['accounts'] ?? [],
            // Relative dates such as "tomorrow" must use the requested/default
            // scheduling timezone, never the server's configured timezone.
            'current_date' => Carbon::now('Asia/Kolkata')->toDateString(),
            'current_datetime' => Carbon::now('Asia/Kolkata')->toIso8601String(),
            'default_timezone_for_relative_dates' => 'Asia/Kolkata',
        ];

        $userContent = json_encode(
            $userMessage,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        /*
        |--------------------------------------------------------------------------
        | AI PROVIDER FALLBACK ORDER
        |--------------------------------------------------------------------------
        |
        | OpenAI
        |    ↓ failure
        | Anthropic
        |    ↓ failure
        | Gemini
        |    ↓ failure
        | Groq
        |    ↓ failure
        | OpenRouter
        |
        */

        $providers = [
            'openai',
            'anthropic',
            'gemini',
            'groq',
            'openrouter',
        ];

        $errors = [];

        foreach ($providers as $provider) {

            try {

                $content = match ($provider) {

                    default => $this->callProvider($provider, $systemPrompt, $userContent),
                };

                if (!empty($content)) {

                    $result = $this->decodeJsonObject($content);

                    if (
                        is_array($result) &&
                        isset($result['posts']) &&
                        is_array($result['posts'])
                    ) {
                        if ($this->hasValidBulkFields($result['posts'])) {
                            return $result['posts'];
                        }

                        // A model occasionally misses a hard word-count rule.
                        // Give the same provider one focused repair pass before
                        // trying the configured fallback providers.
                        $repairContent = json_encode([
                            'invalid_posts' => $result['posts'],
                            'repair_instruction' => 'Return the same posts in the required JSON schema. Correct every image/graphic text value so it is plain text containing 10 to 20 words. Ensure every content value has a natural, actionable CTA before its hashtags (for example: learn more, get started, contact us, book a demo, visit the website, or reply with a question). Do not alter media URLs or other fields unless necessary. Return JSON only.',
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        $repaired = $this->callProvider(
                            $provider,
                            $systemPrompt,
                            (string) $repairContent
                        );
                        $repairedResult = $this->decodeJsonObject($repaired);
                        if (is_array($repairedResult) && isset($repairedResult['posts']) && is_array($repairedResult['posts']) && $this->hasValidBulkFields($repairedResult['posts'])) {
                            return $repairedResult['posts'];
                        }

                        $errors[$provider] = 'Generated Image/Graphic Text did not contain 10 to 20 words after repair.';
                    }

                    // Keep the more specific validation error recorded above;
                    // replacing it here makes a failed repair look like a JSON error.
                    $errors[$provider] ??= 'Invalid JSON/post structure returned.';
                }

            } catch (\Throwable $e) {

                $errors[$provider] = $e->getMessage();

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT:
                | Continue to next provider.
                |--------------------------------------------------------------------------
                */

                continue;
            }
        }

        throw new RuntimeException($this->generationFailureMessage($errors));
    }

    private function callProvider(string $provider, string $systemPrompt, string $userContent): ?string
    {
        return match ($provider) {
            'openai' => $this->callOpenAI($systemPrompt, $userContent),
            'anthropic' => $this->callAnthropic($systemPrompt, $userContent),
            'gemini' => $this->callGemini($systemPrompt, $userContent),
            'groq' => $this->callGroq($systemPrompt, $userContent),
            'openrouter' => $this->callOpenRouter($systemPrompt, $userContent),
            default => null,
        };
    }

    private function hasValidBulkFields(array $posts): bool
    {
        foreach ($posts as $post) {
            $text = trim((string) ($post['image/graphic text'] ?? ''));
            $wordCount = preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', $text);
            if ($wordCount < 10 || $wordCount > 20) {
                return false;
            }
            if (! $this->hasNaturalCta((string) ($post['content'] ?? ''))) {
                return false;
            }
        }

        return $posts !== [];
    }

    private function hasNaturalCta(string $content): bool
    {
        return (bool) preg_match('/\b(explore|try|build|use|see|start|download|create|register|filter|review|visit|learn|contact|get started|find out|book|request|sign up|join|reach out|send us|message us|tell us|share|reply|comment|follow|subscribe|check out|take a look)\b/i', $content);
    }

    private function decodeJsonObject(string $content): ?array
    {
        $decoded = json_decode(trim($content), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Some compatible OpenRouter models wrap otherwise valid JSON in a
        // markdown fence or a short introductory sentence.
        if (preg_match('/\{.*\}/s', $content, $match)) {
            $decoded = json_decode($match[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function generationFailureMessage(array $errors): string
    {
        $unavailable = [];
        $contentFailures = [];
        foreach ($errors as $provider => $error) {
            $originalError = trim((string) $error);
            $error = mb_strtolower($originalError);
            if (str_contains($error, 'credit') || str_contains($error, 'quota') || str_contains($error, 'billing')) {
                $unavailable[] = ucfirst($provider).' has no available API credit.';
            } elseif (str_contains($error, 'model') && (str_contains($error, 'not found') || str_contains($error, 'no longer available'))) {
                $unavailable[] = ucfirst($provider).' is configured with an unavailable model.';
            } else {
                $contentFailures[] = ucfirst($provider).': '.$originalError;
            }
        }

        $details = array_merge($unavailable, $contentFailures);
        $message = 'AI bulk generation is currently unavailable.';
        if ($details !== []) {
            $message .= ' '.implode(' ', $details);
        }

        if ($unavailable !== []) {
            $message .= ' Add API credit to at least one provider or configure a working provider model, then try again.';
        } elseif ($contentFailures !== []) {
            $message .= ' Check the listed provider/model response and try again.';
        }

        return $message;
    }


    /**
     * OpenAI
     */
    private function callOpenAI(
        string $systemPrompt,
        string $userContent
    ): string {

        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            throw new RuntimeException('OpenAI API key is missing.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => config(
                        'services.openai.model',
                        'gpt-4o-mini'
                    ),

                    'temperature' => 0.7,

                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userContent,
                        ],
                    ],

                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ]
            );

        if (!$response->successful()) {

            throw new RuntimeException(
                'OpenAI failed: ' . $response->body()
            );
        }

        $content = $response->json(
            'choices.0.message.content'
        );

        if (!$content) {
            throw new RuntimeException(
                'OpenAI returned empty response.'
            );
        }

        return $content;
    }


    /**
     * Anthropic Claude
     */
    private function callAnthropic(
        string $systemPrompt,
        string $userContent
    ): string {

        $apiKey = config('services.anthropic.api_key');

        if (!$apiKey) {
            throw new RuntimeException(
                'Anthropic API key is missing.'
            );
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post(
                'https://api.anthropic.com/v1/messages',
                [
                    'model' => config(
                        'services.anthropic.model',
                        'claude-sonnet-4-0'
                    ),

                    'max_tokens' => 8000,

                    'system' => $systemPrompt,

                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $userContent,
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {

            throw new RuntimeException(
                'Anthropic failed: ' . $response->body()
            );
        }

        $content = $response->json(
            'content.0.text'
        );

        if (!$content) {
            throw new RuntimeException(
                'Anthropic returned empty response.'
            );
        }

        return $content;
    }


    /**
     * Google Gemini
     */
    private function callGemini(
        string $systemPrompt,
        string $userContent
    ): string {

        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            throw new RuntimeException(
                'Gemini API key is missing.'
            );
        }

        $model = config(
            'services.gemini.model',
            'gemini-2.0-flash-lite'
        );

        $url =
            'https://generativelanguage.googleapis.com/v1beta/models/' .
            $model .
            ':generateContent?key=' .
            urlencode($apiKey);

        $response = Http::timeout(120)
            ->post(
                $url,
                [
                    'system_instruction' => [
                        'parts' => [
                            [
                                'text' => $systemPrompt,
                            ],
                        ],
                    ],

                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => $userContent,
                                ],
                            ],
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {

            throw new RuntimeException(
                'Gemini failed: ' . $response->body()
            );
        }

        $content = $response->json(
            'candidates.0.content.parts.0.text'
        );

        if (!$content) {
            throw new RuntimeException(
                'Gemini returned empty response.'
            );
        }

        return $content;
    }


    /**
     * Groq
     */
    private function callGroq(
        string $systemPrompt,
        string $userContent
    ): string {

        $apiKey = config('services.groq.api_key');

        if (!$apiKey) {
            throw new RuntimeException(
                'Groq API key is missing.'
            );
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post(
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'model' => config(
                        'services.groq.model',
                        'llama-3.3-70b-versatile'
                    ),

                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userContent,
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {

            throw new RuntimeException(
                'Groq failed: ' . $response->body()
            );
        }

        $content = $response->json(
            'choices.0.message.content'
        );

        if (!$content) {
            throw new RuntimeException(
                'Groq returned empty response.'
            );
        }

        return $content;
    }


    /**
     * OpenRouter
     */
    private function callOpenRouter(
        string $systemPrompt,
        string $userContent
    ): string {

        $apiKey = config('services.openrouter.api_key');

        if (!$apiKey) {
            throw new RuntimeException(
                'OpenRouter API key is missing.'
            );
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->withHeaders([
                'HTTP-Referer' => config(
                    'app.url',
                    'https://yourdomain.com'
                ),
                'X-Title' => config(
                    'app.name',
                    'Social Media Scheduler'
                ),
            ])
            ->post(
                'https://openrouter.ai/api/v1/chat/completions',
                [
                    'model' => config(
                        'services.openrouter.model',
                        'openai/gpt-4o-mini'
                    ),

                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userContent,
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {

            throw new RuntimeException(
                'OpenRouter failed: ' . $response->body()
            );
        }

        $content = $response->json(
            'choices.0.message.content'
        );

        if (!$content) {
            throw new RuntimeException(
                'OpenRouter returned empty response.'
            );
        }

        return $content;
    }
}
