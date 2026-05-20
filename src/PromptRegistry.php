<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts;

use function count;
use function in_array;

use InvalidArgumentException;

use function is_array;
use function is_string;
use function sprintf;

use Token27\NexusAI\Prompts\Contract\PromptInterface;
use Token27\NexusAI\Prompts\Contract\PromptRegistryInterface;
use Token27\NexusAI\Prompts\Contract\StorageInterface;
use Token27\NexusAI\Prompts\Exception\AmbiguousPromptException;
use Token27\NexusAI\Prompts\Exception\PromptNotFoundException;
use Token27\NexusAI\Prompts\Loader\PromptLoader;

final class PromptRegistry implements PromptRegistryInterface
{
    /**
     * In-memory cache.
     * Key: "{source}:{identifier}:{version}:{language}"
     *
     * @var array<string, PromptInterface>
     */
    private array $cache = [];

    /**
     * Manually registered prompts (via register()).
     * Key: same cache key format.
     *
     * @var array<string, PromptInterface>
     */
    private array $manual = [];

    /**
     * Registered directories: source → [namespace → [paths]].
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $directories = [];

    /**
     * Storages per source.
     *
     * @var array<string, StorageInterface>
     */
    private array $storages = [];

    /**
     * Current default language (mutable via setDefaultLanguage).
     */
    private string $currentDefaultLanguage;

    /**
     * Current fallback language.
     */
    private string $currentFallbackLanguage;

    /**
     * Current default version — 'latest' by default (mutable via setDefaultVersion).
     */
    private string $currentDefaultVersion = 'latest';

    public function __construct(
        private readonly PromptLoader $loader,
        private readonly StorageInterface $defaultStorage,
        string $defaultLanguage = 'en',
        string $fallbackLanguage = 'en',
    ) {
        $this->currentDefaultLanguage = $defaultLanguage;
        $this->currentFallbackLanguage = $fallbackLanguage;
    }

    public function resolve(
        string $identifier,
        ?string $version = null,
        ?string $language = null,
        ?string $source = null,
    ): PromptInterface {
        $resolvedVersion = $version ?? $this->currentDefaultVersion;
        $resolvedLang = $language ?? $this->currentDefaultLanguage;

        // Resolve 'latest' to the actual highest version
        if ($resolvedVersion === 'latest') {
            $resolvedVersion = $this->resolveLatestVersion($identifier, $source);
        }

        if ($source !== null) {
            return $this->resolveFromSource($identifier, $resolvedVersion, $resolvedLang, $source);
        }

        // No source given → search all sources
        $found = [];

        foreach ($this->listSources() as $src) {
            try {
                $prompt = $this->resolveFromSource($identifier, $resolvedVersion, $resolvedLang, $src);
                $found[$src] = $prompt;
            } catch (PromptNotFoundException) {
                // Not in this source, keep searching
            }
        }

        if (count($found) === 0) {
            throw PromptNotFoundException::forIdentifier($identifier, $version, $language);
        }

        if (count($found) > 1) {
            throw AmbiguousPromptException::multipleSourcesFound($identifier, array_keys($found));
        }

        return reset($found);
    }

    public function register(PromptInterface $prompt): void
    {
        $key = $this->makeCacheKey($prompt->getSource(), $prompt->getIdentifier(), $prompt->getVersion(), $prompt->getLanguage());
        $this->manual[$key] = $prompt;
        $this->cache[$key] = $prompt;
    }

    public function registerDirectory(string $path, string $namespace, string $source): void
    {
        if (!isset($this->directories[$source])) {
            $this->directories[$source] = [];
        }

        if (!isset($this->directories[$source][$namespace])) {
            $this->directories[$source][$namespace] = [];
        }

        $this->directories[$source][$namespace][] = $path;
    }

    public function registerStorage(string $source, StorageInterface $storage): void
    {
        $this->storages[$source] = $storage;
    }

    public function autoloadFrom(string $basePath, ?string $source = null): void
    {
        $basePath = rtrim($basePath, '/\\');
        $promptsPath = $basePath . '/resources/prompts';

        if (!$this->defaultStorage->exists($promptsPath)) {
            return;
        }

        // Determine source name from composer.json if not provided
        if ($source === null) {
            $source = $this->readComposerName($basePath);
        }

        // Scan each subdirectory under resources/prompts/ as a namespace
        $namespaces = $this->defaultStorage->listDirectories($promptsPath);

        foreach ($namespaces as $namespace) {
            $namespacePath = $promptsPath . '/' . $namespace;
            $this->registerDirectory($namespacePath, $namespace, $source);
        }
    }

    public function has(
        string $identifier,
        ?string $version = null,
        ?string $language = null,
        ?string $source = null,
    ): bool {
        try {
            $this->resolve($identifier, $version, $language, $source);

            return true;
        } catch (PromptNotFoundException | AmbiguousPromptException) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function listVersions(string $identifier, ?string $source = null): array
    {
        [$namespace, $type] = $this->splitIdentifier($identifier);
        $versions = [];

        foreach ($this->getSourcesToSearch($source) as $src) {
            foreach ($this->getDirectoriesForNamespace($src, $namespace) as $path) {
                $storage = $this->storages[$src] ?? $this->defaultStorage;
                $found = $this->loader->discoverVersions($storage, $path, $type);

                foreach ($found as $v) {
                    if (!in_array($v, $versions, true)) {
                        $versions[] = $v;
                    }
                }
            }
        }

        usort($versions, static fn(string $a, string $b): int => version_compare($a, $b));

        return $versions;
    }

    /**
     * @return list<string>
     */
    public function listLanguages(string $identifier, string $version, ?string $source = null): array
    {
        [$namespace, $type] = $this->splitIdentifier($identifier);
        $languages = [];

        foreach ($this->getSourcesToSearch($source) as $src) {
            foreach ($this->getDirectoriesForNamespace($src, $namespace) as $path) {
                $storage = $this->storages[$src] ?? $this->defaultStorage;
                $found = $this->loader->discoverLanguages($storage, $path, $type, $version);

                foreach ($found as $lang) {
                    if (!in_array($lang, $languages, true)) {
                        $languages[] = $lang;
                    }
                }
            }
        }

        sort($languages);

        return $languages;
    }

    /**
     * @return list<string>
     */
    public function listIdentifiers(?string $source = null): array
    {
        $identifiers = [];

        foreach ($this->getSourcesToSearch($source) as $src) {
            if (!isset($this->directories[$src])) {
                continue;
            }

            foreach ($this->directories[$src] as $namespace => $paths) {
                foreach ($paths as $path) {
                    $storage = $this->storages[$src] ?? $this->defaultStorage;
                    $types = $storage->listDirectories($path);

                    foreach ($types as $type) {
                        $id = $namespace . '/' . $type;

                        if (!in_array($id, $identifiers, true)) {
                            $identifiers[] = $id;
                        }
                    }
                }
            }
        }

        // Also include manual registrations
        foreach ($this->manual as $prompt) {
            if ($source !== null && $prompt->getSource() !== $source) {
                continue;
            }

            $id = $prompt->getIdentifier();

            if (!in_array($id, $identifiers, true)) {
                $identifiers[] = $id;
            }
        }

        sort($identifiers);

        return $identifiers;
    }

    /**
     * @return list<string>
     */
    public function listSources(): array
    {
        $sources = array_keys($this->directories);

        // Add sources that only have manual registrations
        foreach ($this->manual as $prompt) {
            if (!in_array($prompt->getSource(), $sources, true)) {
                $sources[] = $prompt->getSource();
            }
        }

        sort($sources);

        return $sources;
    }

    public function getDefaultLanguage(): string
    {
        return $this->currentDefaultLanguage;
    }

    public function setDefaultLanguage(string $language): void
    {
        $this->currentDefaultLanguage = $language;
    }

    public function getFallbackLanguage(): string
    {
        return $this->currentFallbackLanguage;
    }

    public function getDefaultVersion(): string
    {
        return $this->currentDefaultVersion;
    }

    public function setDefaultVersion(string $version): void
    {
        $this->currentDefaultVersion = $version;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function resolveFromSource(
        string $identifier,
        string $version,
        string $language,
        string $source,
    ): PromptInterface {
        $candidates = $this->buildLanguageCascade($language);

        // Check cache (try each language candidate)
        foreach ($candidates as $lang) {
            $key = $this->makeCacheKey($source, $identifier, $version, $lang);

            if (isset($this->cache[$key])) {
                return $this->cache[$key];
            }
        }

        // Try loading from directories
        [$namespace, $type] = $this->splitIdentifier($identifier);

        foreach ($this->getDirectoriesForNamespace($source, $namespace) as $path) {
            $storage = $this->storages[$source] ?? $this->defaultStorage;

            $prompt = $this->loader->load(
                storage: $storage,
                rootPath: $path,
                type: $type,
                version: $version,
                candidateLanguages: $candidates,
                identifier: $identifier,
                source: $source,
            );

            if ($prompt !== null) {
                $key = $this->makeCacheKey($source, $identifier, $version, $prompt->getLanguage());
                $this->cache[$key] = $prompt;

                return $prompt;
            }
        }

        throw PromptNotFoundException::forIdentifier($identifier, $version, $language);
    }

    /**
     * Resolve 'latest' to the actual highest version available.
     */
    private function resolveLatestVersion(string $identifier, ?string $source): string
    {
        $versions = $this->listVersions($identifier, $source);

        if ($versions === []) {
            throw PromptNotFoundException::forIdentifier($identifier, 'latest', null);
        }

        // listVersions already sorted via version_compare, take the last
        return $versions[count($versions) - 1];
    }

    /**
     * Read the "name" field from a composer.json file.
     * Falls back to the directory basename if composer.json is missing.
     */
    private function readComposerName(string $basePath): string
    {
        $composerFile = $basePath . '/composer.json';

        if (file_exists($composerFile)) {
            $content = file_get_contents($composerFile);

            if ($content !== false) {
                /** @var mixed $decoded */
                $decoded = json_decode($content, true);

                if (is_array($decoded) && isset($decoded['name']) && is_string($decoded['name'])) {
                    return $decoded['name'];
                }
            }
        }

        // Fallback: use last two path segments as vendor/package
        $parts = explode('/', str_replace('\\', '/', $basePath));
        $count = count($parts);

        if ($count >= 2) {
            return $parts[$count - 2] . '/' . $parts[$count - 1];
        }

        return $parts[0];
    }

    /**
     * Build fallback language cascade: e.g. 'es_AR' → ['es_AR', 'es', 'en']
     *
     * @return list<string>
     */
    private function buildLanguageCascade(string $language): array
    {
        $candidates = [$language];

        // If regional (e.g. es_AR), add base language (es)
        if (str_contains($language, '_')) {
            $base = explode('_', $language, 2)[0];
            $candidates[] = $base;
        }

        // Add fallback if not already included
        if (!in_array($this->currentFallbackLanguage, $candidates, true)) {
            $candidates[] = $this->currentFallbackLanguage;
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function getSourcesToSearch(?string $source): array
    {
        if ($source !== null) {
            return [$source];
        }

        return $this->listSources();
    }

    /**
     * @return list<string>
     */
    private function getDirectoriesForNamespace(string $source, string $namespace): array
    {
        return $this->directories[$source][$namespace] ?? [];
    }

    /**
     * Split "namespace/type" identifier into [namespace, type].
     *
     * @return array{0: string, 1: string}
     */
    private function splitIdentifier(string $identifier): array
    {
        $parts = explode('/', $identifier, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException(
                sprintf('Invalid identifier format "%s". Expected "namespace/type".', $identifier),
            );
        }

        return [$parts[0], $parts[1]];
    }

    private function makeCacheKey(string $source, string $identifier, string $version, string $language): string
    {
        return $source . ':' . $identifier . ':' . $version . ':' . $language;
    }
}
