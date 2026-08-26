<?php

namespace App\Services;

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
            "media url": "",
            "schedule date": "",
            "schedule time": "",
            "timezone": "",
            "status": ""
        }
    ]
}

IMPORTANT RULES:

1. Generate exactly the number of posts requested by the user.

2. Use ONLY projects provided in the application context.

3. Use ONLY connected accounts/pages provided in the application context.

4. Never invent a project name.

5. Never invent an account/page.

6. The "media url" field MUST ALWAYS be empty.

7. NEVER create, guess, or invent Dropbox URLs.

8. The user will manually create/select images or videos after downloading the generated Excel file.

9. The user will manually add Dropbox media URLs to the Excel file before importing it.

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

18. status must be either Draft or Pending.

18a. Use the exact platform and account/page display values supplied in available_accounts. Each account is tied to a project; only pair it with that project.

19. Do not create dates/times in the past.

20. Generate high-quality social media content according to the user's requested tone, topic and platform.

21. Respect platform character limits:
    Instagram: 2200 characters
    LinkedIn: 3000 characters
    X (Twitter): 280 characters

22. Do not include markdown around the JSON.

23. Do not include explanations outside JSON.

24. If the user does not specify a media URL, ALWAYS leave media url empty.

25. The generated Excel will later be reviewed by the user, who will add media URLs manually.
PROMPT;

        $userMessage = [
            'user_request' => $userPrompt,
            'available_projects' => $context['projects'] ?? [],
            'available_accounts' => $context['accounts'] ?? [],
            'current_date' => now()->toDateString(),
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

                    'openai' => $this->callOpenAI(
                        $systemPrompt,
                        $userContent
                    ),

                    'anthropic' => $this->callAnthropic(
                        $systemPrompt,
                        $userContent
                    ),

                    'gemini' => $this->callGemini(
                        $systemPrompt,
                        $userContent
                    ),

                    'groq' => $this->callGroq(
                        $systemPrompt,
                        $userContent
                    ),

                    'openrouter' => $this->callOpenRouter(
                        $systemPrompt,
                        $userContent
                    ),

                    default => null,
                };

                if (!empty($content)) {

                    $result = json_decode($content, true);

                    if (
                        is_array($result) &&
                        isset($result['posts']) &&
                        is_array($result['posts'])
                    ) {
                        return $result['posts'];
                    }

                    $errors[$provider] = 'Invalid JSON/post structure returned.';
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

        throw new RuntimeException(
            'All AI providers failed. ' .
            json_encode($errors)
        );
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
