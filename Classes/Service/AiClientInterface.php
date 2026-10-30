<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

interface AiClientInterface
{
    public function isConfigured(): bool;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function complete(string $systemPrompt, array $messages): string;
}
