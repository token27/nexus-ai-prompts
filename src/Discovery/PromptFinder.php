<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Discovery;

use function count;
use function dirname;
use function in_array;

use RuntimeException;
use Token27\NexusAI\Prompts\Contract\PromptRegistryInterface;

/**
 * PromptFinder — discovers and catalogs prompts both from the filesystem
 * (zero-config scan) and from a configured PromptRegistry.
 *
 * Two modes of operation:
 *
 * 1. **Static scan** (zero-config, like CakePHP TaskFinder):
 *    PromptFinder::scan();
 *    Auto-detects vendor/ from its own location and scans all packages
 *    for resources/prompts/ directories following the convention:
 *    {package}/resources/prompts/{namespace}/{type}/v{version}/{lang}.json
 *
 * 2. **Registry-based** (queries an already-configured registry):
 *    $finder = new PromptFinder($registry);
 *    $finder->findAll();
 */
final class PromptFinder
{
    public function __construct(
        private readonly ?PromptRegistryInterface $registry = null,
    ) {}

    // =========================================================================
    // Static scan mode — zero-config filesystem discovery
    // =========================================================================

    /**
     * Scan for prompts across all installed packages.
     *
     * With no arguments, auto-detects the vendor directory from this file's
     * location. Scans every package for resources/prompts/ following the
     * convention: {vendor}/{package}/resources/prompts/{namespace}/{type}/v{version}/{lang}.json
     *
     * @param string|null  $basePath            Explicit path to scan (vendor dir or libs dir). Auto-detected if null.
     * @param list<string> $excludeSources      Source names (vendor/package) to skip
     * @param list<string> $excludeNamespaces   Namespace names to skip
     * @param list<string> $excludeIdentifiers  Full identifiers (namespace/type) to skip
     * @param list<string> $onlySources         If set, scan ONLY these sources (overrides excludeSources)
     * @param list<string> $onlyNamespaces      If set, scan ONLY these namespaces (overrides excludeNamespaces)
     *
     * @return list<array{source: string, path: string, prompts: list<array{identifier: string, versions: list<string>, languages_per_version: array<string, list<string>>}>}>
     */
    public static function scan(
        ?string $basePath = null,
        array $excludeSources = [],
        array $excludeNamespaces = [],
        array $excludeIdentifiers = [],
        array $onlySources = [],
        array $onlyNamespaces = [],
    ): array {
        $searchPaths = self::resolveSearchPaths($basePath);
        $results = [];

        foreach ($searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $results = array_merge($results, self::scanDirectory(
                $searchPath,
                $excludeSources,
                $excludeNamespaces,
                $excludeIdentifiers,
                $onlySources,
                $onlyNamespaces,
            ));
        }

        return $results;
    }

    // =========================================================================
    // Registry-based mode — queries a pre-configured registry
    // =========================================================================

    /**
     * Find all identifiers grouped by source (registry-based).
     *
     * @return array<string, list<string>>  source → sorted identifiers
     */
    public function findAll(): array
    {
        $registry = $this->getRegistry();
        $result = [];

        foreach ($registry->listSources() as $source) {
            $result[$source] = $registry->listIdentifiers($source);
        }

        return $result;
    }

    /**
     * Find identifiers from a specific source (registry-based).
     *
     * @return list<string>
     */
    public function findBySource(string $source): array
    {
        return $this->getRegistry()->listIdentifiers($source);
    }

    /**
     * Find identifiers matching a prompt type (registry-based).
     * e.g. findByType('websearch') → ['example/websearch', 'tools/websearch']
     *
     * @return array<string, list<string>>  source → matching identifiers
     */
    public function findByType(string $type): array
    {
        $registry = $this->getRegistry();
        $result = [];

        foreach ($registry->listSources() as $source) {
            $matching = [];

            foreach ($registry->listIdentifiers($source) as $id) {
                if (str_ends_with($id, '/' . $type)) {
                    $matching[] = $id;
                }
            }

            if ($matching !== []) {
                $result[$source] = $matching;
            }
        }

        return $result;
    }

    /**
     * Find identifiers matching a namespace (registry-based).
     *
     * @return array<string, list<string>>  source → matching identifiers
     */
    public function findByNamespace(string $namespace): array
    {
        $registry = $this->getRegistry();
        $result = [];

        foreach ($registry->listSources() as $source) {
            $matching = [];

            foreach ($registry->listIdentifiers($source) as $id) {
                if (str_starts_with($id, $namespace . '/')) {
                    $matching[] = $id;
                }
            }

            if ($matching !== []) {
                $result[$source] = $matching;
            }
        }

        return $result;
    }

    /**
     * Return all registered source names (registry-based).
     *
     * @return list<string>
     */
    public function getSources(): array
    {
        return $this->getRegistry()->listSources();
    }

    /**
     * Return identifiers that appear in more than one source (registry-based).
     *
     * @return array<string, list<string>>  identifier → sources that contain it
     */
    public function getDuplicates(): array
    {

        /** @var array<string, list<string>> $indexedByIdentifier */
        $indexedByIdentifier = [];

        foreach ($this->findAll() as $source => $identifiers) {
            foreach ($identifiers as $id) {
                if (!isset($indexedByIdentifier[$id])) {
                    $indexedByIdentifier[$id] = [];
                }
                $indexedByIdentifier[$id][] = $source;
            }
        }

        $duplicates = [];

        foreach ($indexedByIdentifier as $id => $sources) {
            if (count($sources) > 1) {
                $duplicates[$id] = $sources;
            }
        }

        ksort($duplicates);

        return $duplicates;
    }

    /**
     * Build a flat summary catalog (registry-based).
     *
     * @return list<array{identifier: string, source: string, versions: list<string>, languages_per_version: array<string, list<string>>}>
     */
    public function catalog(): array
    {
        $registry = $this->getRegistry();
        $entries = [];

        foreach ($this->findAll() as $source => $identifiers) {
            foreach ($identifiers as $id) {
                $versions = $registry->listVersions($id, $source);
                $languagesPerVersion = [];

                foreach ($versions as $v) {
                    $languagesPerVersion[$v] = $registry->listLanguages($id, $v, $source);
                }

                $entries[] = [
                    'identifier' => $id,
                    'source' => $source,
                    'versions' => $versions,
                    'languages_per_version' => $languagesPerVersion,
                ];
            }
        }

        return $entries;
    }

    // =========================================================================
    // Internal: static scan helpers
    // =========================================================================

    /**
     * Resolve which directories to scan.
     *
     * If $basePath is provided, scan that directory.
     * Otherwise, auto-detect from this file's location:
     *   1. Typical Composer install: __DIR__ is {vendor}/token27/nexus-ai-prompts/src/Discovery
     *      → vendor = dirname(__DIR__, 4)
     *   2. Sibling libraries: libraryRoot = dirname(__DIR__, 3), parent = dirname(__DIR__, 4)
     *
     * @return list<string>
     */
    private static function resolveSearchPaths(?string $basePath): array
    {
        if ($basePath !== null) {
            return [rtrim($basePath, '/\\')];
        }

        $paths = [];

        // __DIR__ = .../vendor/token27/nexus-ai-prompts/src/Discovery
        // vendorDir = .../vendor (4 levels up)
        $vendorDir = dirname(__DIR__, 4);

        if (is_dir($vendorDir)) {
            $paths[] = $vendorDir;
        }

        // Also check the parent of the library root (for sibling-library setups)
        // libraryRoot = .../nexus-ai-prompts (3 levels up from Discovery)
        // parentDir   = .../ (4 levels up — same as vendorDir in most cases)
        // In a non-Composer layout like /project/libs/nexus-ai-prompts,
        // vendorDir would be /project/libs which is what we want.

        return $paths;
    }

    /**
     * Scan a directory tree for packages containing resources/prompts/.
     *
     * Supports two structures:
     *   - Composer: {basePath}/{vendor}/{package}/resources/prompts/
     *   - Flat:     {basePath}/{package}/resources/prompts/
     *
     * @param list<string> $excludeSources
     * @param list<string> $excludeNamespaces
     * @param list<string> $excludeIdentifiers
     * @param list<string> $onlySources
     * @param list<string> $onlyNamespaces
     *
     * @return list<array{source: string, path: string, prompts: list<array{identifier: string, versions: list<string>, languages_per_version: array<string, list<string>>}>}>
     */
    private static function scanDirectory(
        string $basePath,
        array $excludeSources,
        array $excludeNamespaces,
        array $excludeIdentifiers,
        array $onlySources,
        array $onlyNamespaces,
    ): array {
        $results = [];

        // Try Composer layout: {basePath}/{vendor}/{package}/
        foreach (self::listSubdirs($basePath) as $vendorName) {
            $vendorPath = $basePath . '/' . $vendorName;

            // Skip dot-directories and non-directories
            if (!is_dir($vendorPath) || $vendorName[0] === '.') {
                continue;
            }

            foreach (self::listSubdirs($vendorPath) as $packageName) {
                $packagePath = $vendorPath . '/' . $packageName;
                $promptsPath = $packagePath . '/resources/prompts';
                $source = $vendorName . '/' . $packageName;

                if (!is_dir($promptsPath)) {
                    // Also check if this is a flat layout (no vendor nesting)
                    // i.e., {basePath}/{libraryName}/resources/prompts
                    $flatPromptsPath = $vendorPath . '/resources/prompts';

                    if (is_dir($flatPromptsPath)) {
                        $flatSource = $vendorName;

                        if (self::shouldIncludeSource($flatSource, $excludeSources, $onlySources)) {
                            $entry = self::scanPromptsDirectory(
                                $flatPromptsPath,
                                $flatSource,
                                $excludeNamespaces,
                                $excludeIdentifiers,
                                $onlyNamespaces,
                            );

                            if ($entry !== null) {
                                $results[] = $entry;
                            }
                        }
                    }

                    continue;
                }

                if (!self::shouldIncludeSource($source, $excludeSources, $onlySources)) {
                    continue;
                }

                $entry = self::scanPromptsDirectory(
                    $promptsPath,
                    $source,
                    $excludeNamespaces,
                    $excludeIdentifiers,
                    $onlyNamespaces,
                );

                if ($entry !== null) {
                    $results[] = $entry;
                }
            }
        }

        return $results;
    }

    /**
     * Scan a resources/prompts/ directory for namespaces, types, versions, and languages.
     *
     * @param list<string> $excludeNamespaces
     * @param list<string> $excludeIdentifiers
     * @param list<string> $onlyNamespaces
     *
     * @return array{source: string, path: string, prompts: list<array{identifier: string, versions: list<string>, languages_per_version: array<string, list<string>>}>}|null
     */
    private static function scanPromptsDirectory(
        string $promptsPath,
        string $source,
        array $excludeNamespaces,
        array $excludeIdentifiers,
        array $onlyNamespaces,
    ): ?array {
        $prompts = [];

        foreach (self::listSubdirs($promptsPath) as $namespace) {
            if (!self::shouldIncludeNamespace($namespace, $excludeNamespaces, $onlyNamespaces)) {
                continue;
            }

            $namespacePath = $promptsPath . '/' . $namespace;

            foreach (self::listSubdirs($namespacePath) as $type) {
                $identifier = $namespace . '/' . $type;

                if (in_array($identifier, $excludeIdentifiers, true)) {
                    continue;
                }

                $typePath = $namespacePath . '/' . $type;
                $versions = [];
                $languagesPerVersion = [];

                foreach (self::listSubdirs($typePath) as $versionDir) {
                    if (!str_starts_with($versionDir, 'v')) {
                        continue;
                    }

                    $version = substr($versionDir, 1); // strip 'v' prefix
                    $versionPath = $typePath . '/' . $versionDir;
                    $languages = [];

                    foreach (scandir($versionPath) ?: [] as $file) {
                        if (str_ends_with($file, '.json')) {
                            $languages[] = substr($file, 0, -5); // strip .json
                        }
                    }

                    if ($languages !== []) {
                        sort($languages);
                        $versions[] = $version;
                        $languagesPerVersion[$version] = $languages;
                    }
                }

                if ($versions !== []) {
                    usort($versions, static fn(string $a, string $b): int => version_compare($a, $b));

                    $prompts[] = [
                        'identifier' => $identifier,
                        'versions' => $versions,
                        'languages_per_version' => $languagesPerVersion,
                    ];
                }
            }
        }

        if ($prompts === []) {
            return null;
        }

        return [
            'source' => $source,
            'path' => $promptsPath,
            'prompts' => $prompts,
        ];
    }

    /**
     * @return list<string>
     */
    private static function listSubdirs(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $dirs = [];

        foreach (scandir($path) ?: [] as $item) {
            if ($item[0] === '.') {
                continue;
            }

            if (is_dir($path . '/' . $item)) {
                $dirs[] = $item;
            }
        }

        sort($dirs);

        return $dirs;
    }

    /**
     * @param list<string> $excludeSources
     * @param list<string> $onlySources
     */
    private static function shouldIncludeSource(string $source, array $excludeSources, array $onlySources): bool
    {
        if ($onlySources !== []) {
            return in_array($source, $onlySources, true);
        }

        return !in_array($source, $excludeSources, true);
    }

    /**
     * @param list<string> $excludeNamespaces
     * @param list<string> $onlyNamespaces
     */
    private static function shouldIncludeNamespace(string $namespace, array $excludeNamespaces, array $onlyNamespaces): bool
    {
        if ($onlyNamespaces !== []) {
            return in_array($namespace, $onlyNamespaces, true);
        }

        return !in_array($namespace, $excludeNamespaces, true);
    }

    private function getRegistry(): PromptRegistryInterface
    {
        if ($this->registry === null) {
            throw new RuntimeException(
                'PromptFinder registry-based methods require a PromptRegistryInterface. '
                . 'Pass a registry via the constructor or use the static PromptFinder::scan() method.',
            );
        }

        return $this->registry;
    }
}
