<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Contract;

use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Transforms a RenderedPrompt into a specific AI service payload format.
 *
 * Built-in formatters cover the most common services (OpenAI, Anthropic, Gemini, etc.).
 * Implement this interface for custom/exotic services (ComfyUI workflows, Replicate, etc.).
 */
interface OutputFormatterInterface
{
    /**
     * Unique name of this formatter (e.g., "openai-chat", "stability-ai").
     */
    public function getName(): string;

    /**
     * Whether this formatter can handle the given rendered prompt.
     */
    public function supports(RenderedPrompt $prompt): bool;

    /**
     * Transform the RenderedPrompt into the format expected by the target API.
     *
     * @return mixed  string|array|object depending on the target API
     */
    public function format(RenderedPrompt $prompt): mixed;
}
