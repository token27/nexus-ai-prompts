<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for Google Gemini API — contents with parts.
 *
 * Output: ['contents' => [['role' => 'user', 'parts' => [['text' => '...']]]]]
 */
final class GeminiFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'gemini';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return $prompt->hasRoles();
    }

    /**
     * @return array{contents: list<array{role: string, parts: list<array<string, mixed>>}>}
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asGemini();
    }
}
