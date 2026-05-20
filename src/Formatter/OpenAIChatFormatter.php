<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for OpenAI Chat API, Mistral, Groq, OpenRouter.
 *
 * Output: [['role' => 'system', 'content' => '...'], ['role' => 'user', 'content' => '...']]
 */
final class OpenAIChatFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'openai-chat';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return $prompt->hasRoles();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asOpenAI();
    }
}
