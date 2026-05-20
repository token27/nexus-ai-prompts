<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use function dirname;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Token27\NexusAI\Prompts\Discovery\PromptFinder;

final class PromptFinderScanTest extends TestCase
{
    private string $scanFixtures;

    protected function setUp(): void
    {
        // Fixtures follow the scan convention:
        // {scanFixtures}/{vendor}/{package}/resources/prompts/{namespace}/{type}/v{version}/{lang}.json
        $this->scanFixtures = dirname(__DIR__) . '/fixtures/scan';
    }

    public function testScanWithExplicitPath(): void
    {
        $results = PromptFinder::scan(basePath: $this->scanFixtures);

        static::assertIsArray($results);
        static::assertNotEmpty($results);

        $sources = array_column($results, 'source');
        static::assertContains('vendor-a/package-a', $sources);
    }

    public function testScanDetectsPromptsInPackage(): void
    {
        $results = PromptFinder::scan(basePath: $this->scanFixtures);

        $packageA = null;

        foreach ($results as $entry) {
            if ($entry['source'] === 'vendor-a/package-a') {
                $packageA = $entry;
                break;
            }
        }

        static::assertNotNull($packageA, 'vendor-a/package-a not found in scan results');
        static::assertNotEmpty($packageA['prompts']);

        $websearch = null;

        foreach ($packageA['prompts'] as $prompt) {
            if ($prompt['identifier'] === 'example/websearch') {
                $websearch = $prompt;
                break;
            }
        }

        static::assertNotNull($websearch, 'example/websearch not found');
        static::assertContains('1.0.0', $websearch['versions']);
        static::assertContains('2.0.0', $websearch['versions']);
    }

    public function testScanExcludeSources(): void
    {
        $results = PromptFinder::scan(
            basePath: $this->scanFixtures,
            excludeSources: ['vendor-b/package-b'],
        );

        foreach ($results as $entry) {
            static::assertNotSame('vendor-b/package-b', $entry['source']);
        }

        static::assertNotEmpty($results);
    }

    public function testScanOnlySources(): void
    {
        $results = PromptFinder::scan(
            basePath: $this->scanFixtures,
            onlySources: ['vendor-a/package-a'],
        );

        static::assertCount(1, $results);
        static::assertSame('vendor-a/package-a', $results[0]['source']);
    }

    public function testScanExcludeIdentifiers(): void
    {
        $results = PromptFinder::scan(
            basePath: $this->scanFixtures,
            onlySources: ['vendor-a/package-a'],
            excludeIdentifiers: ['example/websearch'],
        );

        $identifiersFound = [];

        foreach ($results as $entry) {
            foreach ($entry['prompts'] as $prompt) {
                $identifiersFound[] = $prompt['identifier'];
            }
        }

        static::assertNotContains('example/websearch', $identifiersFound);
    }

    public function testScanReturnsVersionsSorted(): void
    {
        $results = PromptFinder::scan(
            basePath: $this->scanFixtures,
            onlySources: ['vendor-a/package-a'],
        );

        static::assertNotEmpty($results);

        foreach ($results[0]['prompts'] as $prompt) {
            if ($prompt['identifier'] === 'example/websearch') {
                $versions = $prompt['versions'];
                static::assertSame('1.0.0', $versions[0]);
                static::assertSame('2.0.0', $versions[1]);
            }
        }
    }

    public function testScanWithNonExistentPathReturnsEmpty(): void
    {
        $results = PromptFinder::scan(basePath: '/nonexistent/path/surely');

        static::assertSame([], $results);
    }

    public function testScanFindsMultipleVendors(): void
    {
        $results = PromptFinder::scan(basePath: $this->scanFixtures);

        $sources = array_column($results, 'source');
        static::assertContains('vendor-a/package-a', $sources);
        static::assertContains('vendor-b/package-b', $sources);
    }

    public function testRegistryBasedMethodsThrowWithoutRegistry(): void
    {
        $finder = new PromptFinder();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PromptRegistryInterface');

        $finder->findAll();
    }
}
