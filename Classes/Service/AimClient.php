<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

use B13\Aim\Ai;
use B13\Aim\Capability\ConversationCapableInterface;
use B13\Aim\Domain\Repository\ProviderConfigurationRepository;
use B13\Aim\Registry\AiProviderRegistry;
use B13\Aim\Registry\DisabledModelRegistry;
use B13\Aim\Request\Message\AssistantMessage;
use B13\Aim\Request\Message\UserMessage;

/**
 * Only registered when EXT:aim is installed, see Configuration/Services.php.
 */
final class AimClient implements AiClientInterface
{
    // Thinking counts toward max_tokens, so the limit must cover more than the reply.
    private const MAX_TOKENS = 8000;
    private const EXTENSION_KEY = 'dkd_fal_photo_browser';

    public function __construct(
        private readonly Ai $ai,
        private readonly ProviderConfigurationRepository $configurationRepository,
        private readonly AiProviderRegistry $providerRegistry,
        private readonly DisabledModelRegistry $disabledModelRegistry,
    ) {}

    /**
     * Installation-wide on purpose: EXT:aim enforces per-user denials itself, they must not
     * fall back to the direct client. A failed lookup propagates for the same reason.
     */
    public function isConfigured(): bool
    {
        foreach ($this->configurationRepository->findAllEnabled() as $configuration) {
            if ($configuration->disabled
                || !$this->providerRegistry->hasProvider($configuration->providerIdentifier)
                || $this->disabledModelRegistry->isDisabled($configuration->providerIdentifier, $configuration->model)
            ) {
                continue;
            }
            if ($this->providerRegistry->getProvider($configuration->providerIdentifier)->hasCapability(ConversationCapableInterface::class)) {
                return true;
            }
        }

        return false;
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
