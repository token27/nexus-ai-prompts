<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Formatter;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Formats for Embedding APIs — single input or array of inputs.
 *
 * Perfect for: OpenAI Embeddings, Cohere Embed.
 * Output: ['input' => '...'] or ['input' => ['...', '...']]
 */
final class EmbeddingFormatter implements OutputFormatterInterface
{
    public function getName(): string
    {
        return 'embedding';
    }

    public function supports(RenderedPrompt $prompt): bool
    {
        return true;
    }

    /**
     * @return array{input: string|list<string>}
     */
    public function format(RenderedPrompt $prompt): array
    {
        return $prompt->asEmbedding();
    }
}
