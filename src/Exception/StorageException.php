<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Exception;

use RuntimeException;

use function sprintf;

use Throwable;

class StorageException extends RuntimeException
{
    public static function readFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to read file: %s', $path),
            0,
            $previous,
        );
    }

    public static function pathNotFound(string $path): self
    {
        return new self(sprintf('Path not found: %s', $path));
    }
}
