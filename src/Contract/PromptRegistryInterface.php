<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Contract;

interface PromptRegistryInterface
{
    public function resolve(
        string $identifier,
        ?string $version = null,
        ?string $language = null,
        ?string $source = null,
    ): PromptInterface;

    public function register(PromptInterface $prompt): void;

    public function registerDirectory(string $path, string $namespace, string $source): void;

    public function has(
        string $identifier,
        ?string $version = null,
        ?string $language = null,
        ?string $source = null,
    ): bool;

    /**
     * Auto-discover and register all prompts found under a library's base path.
     *
     * Scans {basePath}/resources/prompts/{namespace}/ and registers each
     * namespace directory. Reads the source name from composer.json if
     * not provided.
     */
    public function autoloadFrom(string $basePath, ?string $source = null): void;

    /**
     * @return list<string>
     */
    public function listVersions(string $identifier, ?string $source = null): array;

    /**
     * @return list<string>
     */
    public function listLanguages(string $identifier, string $version, ?string $source = null): array;

    /**
     * @return list<string>
     */
    public function listIdentifiers(?string $source = null): array;

    /**
     * @return list<string>
     */
    public function listSources(): array;

    public function getDefaultLanguage(): string;

    public function setDefaultLanguage(string $language): void;

    public function getFallbackLanguage(): string;

    public function getDefaultVersion(): string;

    public function setDefaultVersion(string $version): void;
}
