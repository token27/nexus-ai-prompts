<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Loader;

use function is_array;
use function sprintf;

use Token27\NexusAI\Prompts\Contract\StorageInterface;
use Token27\NexusAI\Prompts\Contract\TemplateEngineInterface;
use Token27\NexusAI\Prompts\Exception\InvalidPromptSchemaException;
use Token27\NexusAI\Prompts\Exception\StorageException;
use Token27\NexusAI\Prompts\Prompt;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\VariableDef;

final class PromptLoader
{
    public function __construct(
        private readonly PromptSchemaValidator $validator,
        private readonly TemplateEngineInterface $engine,
    ) {}

    /**
     * Load a prompt from a specific root directory.
     *
     * Path pattern: {rootPath}/{type}/v{version}/{lang}.json
     *
     * @param string[] $candidateLanguages Ordered list of languages to attempt (e.g. ['es_AR', 'es', 'en'])
     *
     * @throws StorageException
     * @throws InvalidPromptSchemaException
     */
    public function load(
        StorageInterface $storage,
        string $rootPath,
        string $type,
        string $version,
        array $candidateLanguages,
        string $identifier,
        string $source,
    ): ?Prompt {
        foreach ($candidateLanguages as $lang) {
            $path = $this->buildPath($rootPath, $type, $version, $lang);

            if (!$storage->exists($path)) {
                continue;
            }

            $json = $storage->read($path);
            $data = json_decode($json, true);

            if (!is_array($data)) {
                throw new InvalidPromptSchemaException(
                    sprintf('Invalid JSON in prompt file: %s', $path),
                );
            }

            $this->validator->validate($data, $path);

            $blocks = $this->extractBlocks($data);
            $variableDefs = $this->extractVariableDefs($data);
            $metadata = PromptMetadata::fromArray($data['meta']);

            return new Prompt(
                identifier: $identifier,
                version: $version,
                language: $lang,
                source: $source,
                blocks: $blocks,
                variableDefs: $variableDefs,
                metadata: $metadata,
                engine: $this->engine,
            );
        }

        return null;
    }

    /**
     * Discover all available versions for a given type within a root path.
     *
     * @return list<string>
     */
    public function discoverVersions(StorageInterface $storage, string $rootPath, string $type): array
    {
        $typePath = rtrim($rootPath, '/\\') . '/' . $type;
        $dirs = $storage->listDirectories($typePath);

        $versions = [];

        foreach ($dirs as $dir) {
            // Strip the leading 'v' prefix: 'v1.0.0' → '1.0.0'
            if (str_starts_with($dir, 'v')) {
                $versions[] = substr($dir, 1);
            }
        }

        return $versions;
    }

    /**
     * Discover all available languages for a given type+version within a root path.
     *
     * @return list<string>
     */
    public function discoverLanguages(StorageInterface $storage, string $rootPath, string $type, string $version): array
    {
        $versionPath = rtrim($rootPath, '/\\') . '/' . $type . '/v' . $version;
        $files = $storage->listFiles($versionPath);

        $languages = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.json')) {
                $languages[] = substr($file, 0, -5); // strip .json
            }
        }

        return $languages;
    }

    private function buildPath(string $rootPath, string $type, string $version, string $lang): string
    {
        return rtrim($rootPath, '/\\') . '/' . $type . '/v' . $version . '/' . $lang . '.json';
    }

    /**
     * Extract content blocks from the JSON data.
     *
     * In v1.0.0, only the 'blocks' key is supported.
     *
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function extractBlocks(array $data): array
    {
        return $data['blocks'];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, VariableDef>
     */
    private function extractVariableDefs(array $data): array
    {
        $defs = [];
        $variables = $data['variables'] ?? [];

        if (!is_array($variables)) {
            return [];
        }

        foreach ($variables as $name => $def) {
            if (is_array($def)) {
                $defs[(string) $name] = VariableDef::fromArray((string) $name, $def);
            }
        }

        return $defs;
    }
}
