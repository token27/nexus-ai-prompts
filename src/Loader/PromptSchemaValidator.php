<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Loader;

use function array_key_exists;
use function count;
use function is_array;

use Token27\NexusAI\Prompts\Exception\InvalidPromptSchemaException;

final class PromptSchemaValidator
{
    /**
     * Validate the decoded JSON array against the expected prompt schema.
     *
     * In v1.0.0, only 'blocks' is supported as the content key.
     * Each block requires 'content' as a minimum. 'role' is OPTIONAL.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidPromptSchemaException
     */
    public function validate(array $data, string $sourcePath = ''): void
    {
        // --- meta ---
        if (!array_key_exists('meta', $data)) {
            throw InvalidPromptSchemaException::missingField('meta', $sourcePath);
        }

        if (!is_array($data['meta'])) {
            throw InvalidPromptSchemaException::invalidField('meta', 'must be an object', $sourcePath);
        }

        foreach (['version', 'prompt_type', 'language'] as $required) {
            if (empty($data['meta'][$required])) {
                throw InvalidPromptSchemaException::missingField("meta.{$required}", $sourcePath);
            }
        }

        // --- blocks (mandatory in v1.0.0) ---
        if (!array_key_exists('blocks', $data)) {
            throw InvalidPromptSchemaException::missingField('blocks', $sourcePath);
        }

        if (!is_array($data['blocks']) || count($data['blocks']) === 0) {
            throw InvalidPromptSchemaException::invalidField('blocks', 'must be a non-empty array', $sourcePath);
        }

        foreach ($data['blocks'] as $index => $block) {
            if (!is_array($block)) {
                throw InvalidPromptSchemaException::invalidField(
                    "blocks[{$index}]",
                    'must be an object',
                    $sourcePath,
                );
            }

            // Only 'content' is required per block. 'role' is optional.
            if (!array_key_exists('content', $block)) {
                throw InvalidPromptSchemaException::missingField("blocks[{$index}].content", $sourcePath);
            }
        }

        // --- variables ---
        if (!array_key_exists('variables', $data)) {
            throw InvalidPromptSchemaException::missingField('variables', $sourcePath);
        }

        if (!is_array($data['variables'])) {
            throw InvalidPromptSchemaException::invalidField('variables', 'must be an object', $sourcePath);
        }
    }
}
