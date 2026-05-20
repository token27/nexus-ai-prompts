<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for Ollama chat endpoint.
 *
 * Output: ['messages' => [['role' => 'system', 'content' => '...'], ...]]
 */
final class OllamaChatFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'ollama-chat';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return $prompt->hasRoles();
    }

    /**
     * @return array{messages: list<array{role: string, content: string}>}
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asOllama();
    }
}
