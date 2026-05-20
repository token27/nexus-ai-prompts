<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for Stability AI API — weighted text prompts.
 *
 * Output: ['text_prompts' => [['text' => '...', 'weight' => 1.0], ...]]
 */
final class StabilityAIFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'stability-ai';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return true;
    }

    /**
     * @return array{text_prompts: list<array{text: string, weight: float}>}
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asStabilityAI();
    }
}
