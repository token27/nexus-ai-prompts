<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\Engine\MustacheAdapter;
use Token27\NexusAI\Prompts\Exception\VariableValidationException;
use Token27\NexusAI\Prompts\Prompt;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\VariableDef;

final class PromptTest extends TestCase
{
    private MustacheAdapter $engine;
    private PromptMetadata $metadata;

    protected function setUp(): void
    {
        $this->engine = new MustacheAdapter();
        $this->metadata = new PromptMetadata(
            version: '1.0.0',
            promptType: 'test',
            language: 'en',
        );
    }

    public function testRenderWithValidVariables(): void
    {
        $prompt = $this->makePrompt(
            blocks: [
                ['role' => 'system', 'content' => 'You are a {{role}} assistant.'],
                ['role' => 'user', 'content' => 'Hello, {{name}}!'],
            ],
            variableDefs: [
                'role' => VariableDef::fromArray('role', ['type' => 'string', 'required' => true]),
                'name' => VariableDef::fromArray('name', ['type' => 'string', 'required' => true]),
            ],
        );

        $rendered = $prompt->render(['role' => 'helpful', 'name' => 'World']);
        $blocks = $rendered->getBlocks();

        static::assertCount(2, $blocks);
        static::assertSame('system', $blocks[0]['role']);
        static::assertSame('You are a helpful assistant.', $blocks[0]['content']);
        static::assertSame('Hello, World!', $blocks[1]['content']);
        static::assertSame('1.0.0', $rendered->version);
        static::assertSame('en', $rendered->language);
        static::assertSame('test-source', $rendered->source);
    }

    public function testRenderThrowsOnMissingRequiredVariable(): void
    {
        $prompt = $this->makePrompt(
            blocks: [['role' => 'user', 'content' => 'Topic: {{topic}}']],
            variableDefs: [
                'topic' => VariableDef::fromArray('topic', ['type' => 'string', 'required' => true]),
            ],
        );

        $this->expectException(VariableValidationException::class);
        $this->expectExceptionMessage('topic');

        $prompt->render([]);
    }

    public function testRenderAppliesDefaultForOptionalVariable(): void
    {
        $prompt = $this->makePrompt(
            blocks: [['role' => 'user', 'content' => 'Queries: {{count}}']],
            variableDefs: [
                'count' => VariableDef::fromArray('count', ['type' => 'number', 'required' => false, 'default' => 5]),
            ],
        );

        $rendered = $prompt->render([]);
        $blocks = $rendered->getBlocks();

        static::assertSame('Queries: 5', $blocks[0]['content']);
    }

    public function testRenderMultipleMissingRequired(): void
    {
        $prompt = $this->makePrompt(
            blocks: [['role' => 'user', 'content' => '{{a}} and {{b}}']],
            variableDefs: [
                'a' => VariableDef::fromArray('a', ['type' => 'string', 'required' => true]),
                'b' => VariableDef::fromArray('b', ['type' => 'string', 'required' => true]),
            ],
        );

        $this->expectException(VariableValidationException::class);

        $prompt->render([]);
    }

    public function testRenderGettersReturnCorrectValues(): void
    {
        $prompt = $this->makePrompt();

        static::assertSame('example/test', $prompt->getIdentifier());
        static::assertSame('1.0.0', $prompt->getVersion());
        static::assertSame('en', $prompt->getLanguage());
        static::assertSame('test-source', $prompt->getSource());
    }

    public function testRenderedPromptHelpers(): void
    {
        $prompt = $this->makePrompt(
            blocks: [
                ['role' => 'system', 'content' => 'System message.'],
                ['role' => 'user', 'content' => 'User message.'],
            ],
        );

        $rendered = $prompt->render([]);

        static::assertSame('System message.', $rendered->getSystemMessage());
        static::assertSame('User message.', $rendered->getUserMessage());
    }

    public function testGetBlocksReturnsRawBlocks(): void
    {
        $prompt = $this->makePrompt(
            blocks: [
                ['role' => 'system', 'content' => 'System.'],
                ['role' => 'user', 'content' => 'User.'],
            ],
        );

        static::assertCount(2, $prompt->getBlocks());
        static::assertSame('system', $prompt->getBlocks()[0]['role']);
    }

    public function testRenderPreservesExtraBlockKeys(): void
    {
        // Demonstrates that non-role/content keys (e.g. 'weight') are preserved
        $prompt = $this->makePrompt(
            blocks: [
                ['content' => 'A cat in space', 'weight' => 0.9],
            ],
        );

        $rendered = $prompt->render([]);
        $blocks = $rendered->getBlocks();

        static::assertSame(0.9, $blocks[0]['weight']);
    }

    public function testRenderBlocksWithoutRoles(): void
    {
        // Image / audio prompts — no role needed
        $prompt = $this->makePrompt(
            blocks: [
                ['content' => 'Photo of a {{subject}}'],
            ],
            variableDefs: [
                'subject' => VariableDef::fromArray('subject', ['type' => 'string', 'required' => true]),
            ],
        );

        $rendered = $prompt->render(['subject' => 'mountain landscape']);

        static::assertSame('Photo of a mountain landscape', $rendered->asPlainString());
        static::assertFalse($rendered->hasRoles());
    }

    // -------------------------------------------------------------------------

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, VariableDef> $variableDefs
     */
    private function makePrompt(
        array $blocks = [['role' => 'user', 'content' => 'Hello']],
        array $variableDefs = [],
    ): Prompt {
        return new Prompt(
            identifier: 'example/test',
            version: '1.0.0',
            language: 'en',
            source: 'test-source',
            blocks: $blocks,
            variableDefs: $variableDefs,
            metadata: $this->metadata,
            engine: $this->engine,
        );
    }
}
