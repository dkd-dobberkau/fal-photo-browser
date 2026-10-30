<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AnthropicClient implements AiClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-5-5';
    // Thinking counts toward max_tokens, so the limit must cover more than the reply.
    private const MAX_TOKENS = 8000;

    private HttpClientInterface $httpClient;
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: null;
        $this->httpClient = HttpClient::create();
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    public function complete(string $systemPrompt, array $messages): string
    {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'output_config' => ['effort' => 'low'],
                'system' => $systemPrompt,
                'messages' => $messages,
            ],
        ]);

        return $this->extractText($response->toArray());
    }

    /**
     * Reads text by block type, responses can start with a thinking block.
     *
     * @param array<string, mixed> $data
     */
    private function extractText(array $data): string
    {
        if (($data['stop_reason'] ?? null) === 'refusal') {
            return 'Diese Anfrage kann ich leider nicht bearbeiten.';
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }
}
