<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiChatService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-5-20250929';

    private HttpClientInterface $httpClient;
    private ?string $apiKey;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a photo search assistant integrated into a TYPO3 backend module that searches Unsplash.
Your job is to help users find the right stock photos based on their descriptions.

When a user describes what they need:
1. Respond with a brief, helpful message in the SAME LANGUAGE the user writes in
2. Generate optimized English search terms for the Unsplash API
3. Suggest orientation and color filters when appropriate
4. Offer 1-2 follow-up suggestions to refine the search

Available orientation values: landscape, portrait, squarish
Available color values: black_and_white, black, white, yellow, orange, red, purple, magenta, green, teal, blue

You MUST respond ONLY with valid JSON in this exact format:
{
    "message": "Your conversational response in the user's language",
    "searchTerms": "primary english search query for unsplash",
    "alternativeTerms": ["alternative search 1", "alternative search 2"],
    "orientation": null,
    "color": null,
    "suggestions": ["Follow-up suggestion 1", "Follow-up suggestion 2"]
}

Rules:
- searchTerms must be in English (Unsplash works best with English queries)
- orientation and color should be null unless clearly implied by the user's description
- Keep your message concise (1-2 sentences)
- suggestions should help the user refine their search
- Do NOT wrap the JSON in markdown code blocks
PROMPT;

    public function __construct()
    {
        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: null;
        $this->httpClient = HttpClient::create();
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * @param array<int, array{role: string, content: string}> $conversationHistory
     * @return array{message: string, searchTerms: string, alternativeTerms: string[], orientation: ?string, color: ?string, suggestions: string[]}
     */
    public function chat(string $userMessage, array $conversationHistory = []): array
    {
        $messages = [];

        foreach ($conversationHistory as $entry) {
            $messages[] = [
                'role' => $entry['role'],
                'content' => $entry['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => 1024,
                'system' => self::SYSTEM_PROMPT,
                'messages' => $messages,
            ],
        ]);

        $data = $response->toArray();
        $content = $data['content'][0]['text'] ?? '';

        // Strip markdown code blocks if present
        $content = trim($content);
        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $parsed = json_decode($content, true);

        if (!is_array($parsed) || !isset($parsed['searchTerms'])) {
            return [
                'message' => $content,
                'searchTerms' => '',
                'alternativeTerms' => [],
                'orientation' => null,
                'color' => null,
                'suggestions' => [],
            ];
        }

        return [
            'message' => $parsed['message'] ?? '',
            'searchTerms' => $parsed['searchTerms'] ?? '',
            'alternativeTerms' => $parsed['alternativeTerms'] ?? [],
            'orientation' => $parsed['orientation'] ?? null,
            'color' => $parsed['color'] ?? null,
            'suggestions' => $parsed['suggestions'] ?? [],
        ];
    }
}
