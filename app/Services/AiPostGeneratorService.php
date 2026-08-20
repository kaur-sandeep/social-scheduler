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
    TikTok
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

17. timezone MUST be a valid IANA timezone.

18. status must be either:
    schedule
    draft

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

        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model'),
                'temperature' => 0.7,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode(
                            $userMessage,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                    ],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI API error: '.$response->body()
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! $content) {
            throw new RuntimeException('AI returned an empty response.');
        }

        $result = json_decode($content, true);

        if (
            ! is_array($result) ||
            ! isset($result['posts']) ||
            ! is_array($result['posts'])
        ) {
            throw new RuntimeException(
                'AI returned an invalid post structure.'
            );
        }

        return $result['posts'];
    }
}