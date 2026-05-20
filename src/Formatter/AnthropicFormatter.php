<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for Anthropic Claude API — system message as top-level key.
 *
 * Output: ['system' => '...', 'messages' => [['role' => 'user', 'content' => '...']]]
 */
final class AnthropicFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'anthropic';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return $prompt->hasRoles();
    }

    /**
     * @return array{system?: string, messages: list<array{role: string, content: string|list<array<string, mixed>>}>}
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asAnthropic();
    }
}
