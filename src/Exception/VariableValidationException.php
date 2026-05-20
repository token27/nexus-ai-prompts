<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Exception;

use RuntimeException;

use function sprintf;

class VariableValidationException extends RuntimeException
{
    /**
     * @param list<string> $missingVariables
     */
    public static function missingRequired(array $missingVariables): self
    {
        return new self(sprintf(
            'Missing required variables: %s',
            implode(', ', $missingVariables),
        ));
    }
}
