<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats as plain concatenated string.
 *
 * Perfect for: DALL-E, StableDiffusion, Midjourney, ElevenLabs, RunwayML, Luma, Suno.
 */
final class PlainStringFormatter implements OutputFormatterInterface
{
    public function __construct(
        private readonly string $separator = "\n\n",
    ) {}

    public function getName(): string
    {
        return 'plain-string';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        // Plain string works with any prompt type
        return true;
    }

    public function format(RenderedPrompt $prompt): string
    {
        return $prompt->asPlainString($this->separator);
    }
}
