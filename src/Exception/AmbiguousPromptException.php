<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Exception;

use RuntimeException;

use function sprintf;

class AmbiguousPromptException extends RuntimeException
{
    /**
     * @param list<string> $sources
     */
    public static function multipleSourcesFound(string $identifier, array $sources): self
    {
        return new self(sprintf(
            'Prompt "%s" found in multiple sources: [%s]. Specify a source explicitly to resolve ambiguity.',
            $identifier,
            implode(', ', $sources),
        ));
    }
}
