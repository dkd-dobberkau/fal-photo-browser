<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

use B13\Aim\Ai;
use B13\Aim\Capability\ConversationCapableInterface;
use B13\Aim\Provider\ProviderResolver;
use B13\Aim\Request\Message\AssistantMessage;
use B13\Aim\Request\Message\UserMessage;

/**
 * Only registered when EXT:aim is installed, see Configuration/Services.php.
 */
final class AimClient implements AiClientInterface
{
    private const MAX_TOKENS = 2000;
    private const EXTENSION_KEY = 'dkd_fal_photo_browser';

    public function __construct(
        private readonly Ai $ai,
        private readonly ProviderResolver $providerResolver,
    ) {}

    public function isConfigured(): bool
    {
        try {
            return $this->providerResolver->resolveAllForCapability(ConversationCapableInterface::class) !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    public function complete(string $systemPrompt, array $messages): string
    {
        $response = $this->ai->conversation(
            messages: array_map(
                static fn(array $message) => $message['role'] === 'assistant'
                    ? new AssistantMessage($message['content'])
                    : new UserMessage($message['content']),
                $messages
            ),
            systemPrompt: $systemPrompt,
            // Prompt fragments from other extensions would break the JSON contract.
            disableSystemPromptComposition: true,
            maxTokens: self::MAX_TOKENS,
            extensionKey: self::EXTENSION_KEY,
        );

        if (!$response->isSuccessful()) {
            throw new \RuntimeException('EXT:aim request failed: ' . implode(' ', $response->errors));
        }

        return $response->content;
    }
}
