<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Exception;

use RuntimeException;

use function sprintf;

class InvalidPromptSchemaException extends RuntimeException
{
    public static function missingField(string $field, string $path): self
    {
        return new self(sprintf('Missing required field "%s" in prompt file "%s"', $field, $path));
    }

    public static function invalidField(string $field, string $reason, string $path): self
    {
        return new self(sprintf('Invalid field "%s" in prompt file "%s": %s', $field, $path, $reason));
    }
}
