<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for Completion/Instruct APIs — single prompt string.
 *
 * Perfect for: GPT-3 Instruct, Ollama /api/generate, local models.
 * Output: ['prompt' => '...']
 */
final class CompletionFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'completion';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return true;
    }

    /**
     * @return array{prompt: string}
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asCompletion();
    }
}
