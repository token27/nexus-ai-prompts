<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Exception;

use RuntimeException;

use function sprintf;

class PromptNotFoundException extends RuntimeException
{
    public static function forIdentifier(string $identifier, ?string $version = null, ?string $language = null): self
    {
        $msg = sprintf('Prompt "%s" not found', $identifier);
        if ($version !== null) {
            $msg .= sprintf(' (version: %s)', $version);
        }
        if ($language !== null) {
            $msg .= sprintf(' (language: %s)', $language);
        }

        return new self($msg);
    }
}
