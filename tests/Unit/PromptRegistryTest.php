<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use function dirname;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\Engine\MustacheAdapter;
use Token27\NexusAI\Prompts\Exception\AmbiguousPromptException;
use Token27\NexusAI\Prompts\Exception\PromptNotFoundException;
use Token27\NexusAI\Prompts\Loader\PromptLoader;
use Token27\NexusAI\Prompts\Loader\PromptSchemaValidator;
use Token27\NexusAI\Prompts\Prompt;
use Token27\NexusAI\Prompts\PromptRegistry;
use Token27\NexusAI\Prompts\Storage\LocalFilesystemStorage;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;

final class PromptRegistryTest extends TestCase
{
    private PromptRegistry $registry;
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
    }

    public function testResolveWithExplicitSource(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        $prompt = $this->registry->resolve('example/websearch', '1.0.0', 'en', 'source-a');

        static::assertSame('example/websearch', $prompt->getIdentifier());
        static::assertSame('1.0.0', $prompt->getVersion());
        static::assertSame('source-a', $prompt->getSource());
    }

    public function testLanguageFallbackFromRegionalToBase(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        // es_AR doesn't exist → falls back to es
        $prompt = $this->registry->resolve('example/websearch', '1.0.0', 'es_AR', 'source-a');

        static::assertSame('es', $prompt->getLanguage());
    }

    public function testLanguageFallbackToDefault(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        // fr doesn't exist → falls back to 'en'
        $prompt = $this->registry->resolve('example/websearch', '1.0.0', 'fr', 'source-a');

        static::assertSame('en', $prompt->getLanguage());
    }

    public function testCacheHitOnSecondResolve(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        $prompt1 = $this->registry->resolve('example/websearch', '1.0.0', 'en', 'source-a');
        $prompt2 = $this->registry->resolve('example/websearch', '1.0.0', 'en', 'source-a');

        // Same instance from cache
        static::assertSame($prompt1, $prompt2);
    }

    public function testThrowsPromptNotFoundExceptionWhenMissing(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        $this->expectException(PromptNotFoundException::class);

        $this->registry->resolve('example/nonexistent', '1.0.0', 'en', 'source-a');
    }

    public function testThrowsAmbiguousPromptExceptionWhenDuplicate(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');
        $this->registry->registerDirectory($this->fixturesPath . '/source-b', 'example', 'source-b');

        $this->expectException(AmbiguousPromptException::class);
        $this->expectExceptionMessage('source-a');
        $this->expectExceptionMessage('source-b');

        // websearch exists in both sources, no source specified
        $this->registry->resolve('example/websearch', '1.0.0', 'en');
    }

    public function testResolveUniqueIdentifierWithoutSource(): void
    {
        // template only exists in source-b
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');
        $this->registry->registerDirectory($this->fixturesPath . '/source-b', 'example', 'source-b');

        $prompt = $this->registry->resolve('example/template', '1.0.0', 'en');

        static::assertSame('source-b', $prompt->getSource());
    }

    public function testListSources(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');
        $this->registry->registerDirectory($this->fixturesPath . '/source-b', 'example', 'source-b');

        $sources = $this->registry->listSources();

        static::assertContains('source-a', $sources);
        static::assertContains('source-b', $sources);
    }

    public function testRegisterManualPrompt(): void
    {
        $metadata = new PromptMetadata(version: '2.0.0', promptType: 'manual', language: 'en');
        $prompt = new Prompt(
            identifier: 'custom/manual',
            version: '2.0.0',
            language: 'en',
            source: 'manual-source',
            blocks: [['role' => 'user', 'content' => 'Manual']],
            variableDefs: [],
            metadata: $metadata,
            engine: new MustacheAdapter(),
        );

        $this->registry->register($prompt);
        $resolved = $this->registry->resolve('custom/manual', '2.0.0', 'en', 'manual-source');

        static::assertSame('custom/manual', $resolved->getIdentifier());
    }

    public function testHasReturnsTrueForExistingPrompt(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        static::assertTrue($this->registry->has('example/websearch', '1.0.0', 'en', 'source-a'));
    }

    public function testHasReturnsFalseForMissingPrompt(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        static::assertFalse($this->registry->has('example/missing', '1.0.0', 'en', 'source-a'));
    }

    public function testResolveLatestVersion(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        // source-a has v1.0.0 and v2.0.0 for websearch
        $prompt = $this->registry->resolve('example/websearch', 'latest', 'en', 'source-a');

        static::assertSame('2.0.0', $prompt->getVersion());
    }

    public function testResolveWithNullVersionUsesLatest(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        // null version → uses defaultVersion which is 'latest'
        $prompt = $this->registry->resolve('example/websearch', null, 'en', 'source-a');

        static::assertSame('2.0.0', $prompt->getVersion());
    }

    public function testSetDefaultLanguageChangesResolution(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        // Default is 'en'
        static::assertSame('en', $this->registry->getDefaultLanguage());

        // Change default to 'es'
        $this->registry->setDefaultLanguage('es');
        static::assertSame('es', $this->registry->getDefaultLanguage());

        // Resolve without language param → uses new default 'es'
        $prompt = $this->registry->resolve('example/websearch', '1.0.0', null, 'source-a');
        static::assertSame('es', $prompt->getLanguage());
    }

    public function testSetDefaultVersionChangesResolution(): void
    {
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        // Default is 'latest' → resolves to 2.0.0
        $prompt1 = $this->registry->resolve('example/websearch', null, 'en', 'source-a');
        static::assertSame('2.0.0', $prompt1->getVersion());

        // Pin to 1.0.0
        $this->registry->setDefaultVersion('1.0.0');
        $prompt2 = $this->registry->resolve('example/websearch', null, 'en', 'source-a');
        static::assertSame('1.0.0', $prompt2->getVersion());

        // Verify getter
        static::assertSame('1.0.0', $this->registry->getDefaultVersion());

        // Back to latest
        $this->registry->setDefaultVersion('latest');
        $prompt3 = $this->registry->resolve('example/websearch', null, 'en', 'source-a');
        static::assertSame('2.0.0', $prompt3->getVersion());
    }

    public function testAutoloadFromRegistersAllNamespaces(): void
    {
        // Use the library's own resources/prompts directory
        $basePath = dirname(__DIR__, 2);

        $this->registry->autoloadFrom($basePath, 'test-autoload');

        $identifiers = $this->registry->listIdentifiers('test-autoload');

        // resources/prompts/example/ exists → namespace 'example'
        // with types: websearch, create-skill, template
        static::assertContains('example/websearch', $identifiers);
        static::assertContains('example/create-skill', $identifiers);
        static::assertContains('example/template', $identifiers);
    }

    public function testResolveMinimalArguments(): void
    {
        // Only 1 source → no ambiguity, null version → latest, null language → default
        $this->registry->registerDirectory($this->fixturesPath . '/source-a', 'example', 'source-a');

        $prompt = $this->registry->resolve('example/websearch');

        static::assertSame('2.0.0', $prompt->getVersion());
        static::assertSame('en', $prompt->getLanguage());
    }
}
