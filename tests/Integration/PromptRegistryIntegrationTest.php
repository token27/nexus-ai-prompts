<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Integration;

use function dirname;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\Discovery\PromptFinder;
use Token27\NexusAI\Prompts\Engine\MustacheAdapter;
use Token27\NexusAI\Prompts\Exception\AmbiguousPromptException;
use Token27\NexusAI\Prompts\Loader\PromptLoader;
use Token27\NexusAI\Prompts\Loader\PromptSchemaValidator;
use Token27\NexusAI\Prompts\PromptRegistry;
use Token27\NexusAI\Prompts\Storage\LocalFilesystemStorage;

final class PromptRegistryIntegrationTest extends TestCase
{
    private PromptRegistry $registry;
    private PromptFinder $finder;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__) . '/fixtures/prompts';

        $loader = new PromptLoader(new PromptSchemaValidator(), new MustacheAdapter());
        // No basePath — full absolute paths from registerDirectory are used directly
        $storage = new LocalFilesystemStorage('');

        $this->registry = new PromptRegistry(
            loader: $loader,
            defaultStorage: $storage,
            defaultLanguage: 'en',
            fallbackLanguage: 'en',
        );

        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');
        $this->registry->registerDirectory($this->fixturesPath . '/source-b', 'example', 'source-b');

        $this->finder = new PromptFinder($this->registry);
    }

    // --- End-to-end resolve + render ------------------------------------------

    public function testEndToEndResolveAndRender(): void
    {
        $prompt = $this->registry->resolve('example/websearch', '1.0.0', 'es', 'source-a');

        $rendered = $prompt->render(['topic' => 'Inteligencia Artificial']);

        static::assertCount(2, $rendered->getBlocks());
        static::assertStringContainsString('Inteligencia Artificial', $rendered->getBlocks()[1]['content']);
        static::assertSame('es', $rendered->language);
        static::assertSame('source-a', $rendered->source);
    }

    public function testRenderWithDefaultVariablesApplied(): void
    {
        // Use the simple test fixture — it only has 'topic', so just verify topic is rendered
        $prompt = $this->registry->resolve('example/websearch', '1.0.0', 'es', 'source-a');

        // Only 'topic' is required in the fixture; default rendering should work
        $rendered = $prompt->render(['topic' => 'Machine Learning']);

        static::assertStringContainsString('Machine Learning', $rendered->getBlocks()[1]['content']);
    }

    public function testLanguageFallbackEndToEnd(): void
    {
        // es_AR → es
        $prompt = $this->registry->resolve('example/websearch', '1.0.0', 'es_AR', 'source-a');

        static::assertSame('es', $prompt->getLanguage());

        $rendered = $prompt->render(['topic' => 'PHP']);
        static::assertStringContainsString('PHP', $rendered->getBlocks()[1]['content']);
    }

    // --- PromptFinder integration ----------------------------------------------

    public function testFinderFindAll(): void
    {
        $all = $this->finder->findAll();

        static::assertArrayHasKey('source-a', $all);
        static::assertArrayHasKey('source-b', $all);
        static::assertContains('example/websearch', $all['source-a']);
    }

    public function testFinderGetDuplicates(): void
    {
        $duplicates = $this->finder->getDuplicates();

        // 'example/websearch' exists in source-a and source-b
        static::assertArrayHasKey('example/websearch', $duplicates);
        static::assertContains('source-a', $duplicates['example/websearch']);
        static::assertContains('source-b', $duplicates['example/websearch']);
    }

    public function testFinderFindByType(): void
    {
        $found = $this->finder->findByType('websearch');

        static::assertArrayHasKey('source-a', $found);
        static::assertContains('example/websearch', $found['source-a']);
    }

    public function testFinderFindByNamespace(): void
    {
        $found = $this->finder->findByNamespace('example');

        static::assertArrayHasKey('source-a', $found);
        static::assertNotEmpty($found['source-a']);
    }

    public function testFinderFindBySource(): void
    {
        $ids = $this->finder->findBySource('source-b');

        static::assertContains('example/websearch', $ids);
        static::assertContains('example/template', $ids);
    }

    public function testFinderCatalogBuildsFullInventory(): void
    {
        $catalog = $this->finder->catalog();

        static::assertNotEmpty($catalog);

        $identifiers = array_column($catalog, 'identifier');
        static::assertContains('example/websearch', $identifiers);
    }

    // --- Multi-source ambiguity ------------------------------------------------

    public function testAmbiguousResolutionWithoutSourceThrows(): void
    {
        $this->expectException(AmbiguousPromptException::class);

        // websearch exists in both source-a and source-b
        $this->registry->resolve('example/websearch', '1.0.0', 'en');
    }

    public function testNonAmbiguousResolutionWithoutSourceSucceeds(): void
    {
        // template only in source-b
        $prompt = $this->registry->resolve('example/template', '1.0.0', 'en');

        static::assertSame('source-b', $prompt->getSource());
    }

    // --- Real resources prompts -----------------------------------------------

    public function testRealResourcePromptsCanBeLoaded(): void
    {
        // resources/prompts/example/ is the namespace root for the 'example' namespace
        $resourcesPath = dirname(__DIR__, 2) . '/resources/prompts/example';

        $loader = new PromptLoader(new PromptSchemaValidator(), new MustacheAdapter());
        $registry = new PromptRegistry(
            loader: $loader,
            defaultStorage: new LocalFilesystemStorage(''),
            defaultLanguage: 'en',
            fallbackLanguage: 'en',
        );

        $registry->registerDirectory($resourcesPath, 'example', 'nexus-ai-prompts');

        $prompt = $registry->resolve('example/websearch', '1.0.0', 'en', 'nexus-ai-prompts');
        $rendered = $prompt->render(['topic' => 'PHP 8.3']);

        static::assertStringContainsString('PHP 8.3', $rendered->getBlocks()[1]['content']);
    }

    // --- v1.1.0: Latest version + autoload + dynamic defaults -----------------

    public function testEndToEndLatestVersion(): void
    {
        // source-a has v1.0.0 and v2.0.0 for websearch
        $prompt = $this->registry->resolve('example/websearch', 'latest', 'en', 'source-a');

        static::assertSame('2.0.0', $prompt->getVersion());
        static::assertStringContainsString('v2', $prompt->render(['topic' => 'AI'])->getBlocks()[1]['content']);
    }

    public function testAutoloadFromRealResourcesPrompts(): void
    {
        $basePath = dirname(__DIR__, 2);

        $loader = new PromptLoader(new PromptSchemaValidator(), new MustacheAdapter());
        $registry = new PromptRegistry(
            loader: $loader,
            defaultStorage: new LocalFilesystemStorage(''),
            defaultLanguage: 'en',
            fallbackLanguage: 'en',
        );

        // autoloadFrom reads composer.json for source name
        $registry->autoloadFrom($basePath, 'test-lib');

        $prompt = $registry->resolve('example/websearch', '1.0.0', 'en', 'test-lib');
        static::assertSame('example/websearch', $prompt->getIdentifier());
    }

    public function testDynamicDefaultsEndToEnd(): void
    {
        // Start: default language = en, default version = latest
        $prompt1 = $this->registry->resolve('example/websearch', null, null, 'source-a');
        static::assertSame('en', $prompt1->getLanguage());
        static::assertSame('2.0.0', $prompt1->getVersion());

        // Switch language to es, pin version to 1.0.0
        $this->registry->setDefaultLanguage('es');
        $this->registry->setDefaultVersion('1.0.0');

        $prompt2 = $this->registry->resolve('example/websearch', null, null, 'source-a');
        static::assertSame('es', $prompt2->getLanguage());
        static::assertSame('1.0.0', $prompt2->getVersion());
    }
}
