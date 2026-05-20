<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\PromptEngine;

final class PromptEngineTest extends TestCase
{
    // ── ::raw() ───────────────────────────────────────────────────────────────

    public function testRawRendersStringTemplate(): void
    {
        $rendered = PromptEngine::raw("A {{animal}} in space", ['animal' => 'cat']);

        static::assertSame('A cat in space', $rendered->asPlainString());
    }

    public function testRawWithNoVariables(): void
    {
        $rendered = PromptEngine::raw("Hello world");

        static::assertSame('Hello world', $rendered->asPlainString());
    }

    public function testRawProducesNoRoleBlock(): void
    {
        $rendered = PromptEngine::raw("Simple prompt");

        static::assertFalse($rendered->hasRoles());
    }

    public function testRawPromptTypeIsRaw(): void
    {
        $rendered = PromptEngine::raw("A prompt");

        static::assertSame('raw', $rendered->getPromptType());
    }

    public function testRawSourceIsRuntime(): void
    {
        $rendered = PromptEngine::raw("A prompt");

        static::assertSame('runtime', $rendered->source);
    }

    // ── ::chat() ──────────────────────────────────────────────────────────────

    public function testChatRendersBlocksWithVariables(): void
    {
        $rendered = PromptEngine::chat([
            ['role' => 'system', 'content' => 'You are {{persona}}'],
            ['role' => 'user', 'content' => '{{task}}'],
        ], ['persona' => 'a poet', 'task' => 'write haiku']);

        $messages = $rendered->asOpenAI();

        static::assertCount(2, $messages);
        static::assertSame('You are a poet', $messages[0]['content']);
        static::assertSame('write haiku', $messages[1]['content']);
    }

    public function testChatDetectsRoles(): void
    {
        $rendered = PromptEngine::chat([
            ['role' => 'system', 'content' => 'You are an assistant'],
        ]);

        static::assertTrue($rendered->hasRoles());
        static::assertSame('chat', $rendered->getPromptType());
    }

    public function testChatWithNoVariables(): void
    {
        $rendered = PromptEngine::chat([
            ['role' => 'user', 'content' => 'Static message'],
        ]);

        static::assertSame('Static message', $rendered->asOpenAI()[0]['content']);
    }

    // ── ::build() ─────────────────────────────────────────────────────────────

    public function testBuildReturnsPromptBuilder(): void
    {
        $builder = PromptEngine::build();

        static::assertInstanceOf(\Token27\NexusAI\Prompts\PromptBuilder::class, $builder);
    }

    // ── Instance methods ──────────────────────────────────────────────────────

    public function testInstanceRenderRaw(): void
    {
        $engine = new PromptEngine();
        $rendered = $engine->renderRaw("Photo of {{subject}}", ['subject' => 'a cat']);

        static::assertSame('Photo of a cat', $rendered->asPlainString());
    }

    public function testInstanceRenderChat(): void
    {
        $engine = new PromptEngine();
        $rendered = $engine->renderChat([
            ['role' => 'user', 'content' => '{{msg}}'],
        ], ['msg' => 'Hello']);

        static::assertSame('Hello', $rendered->asOpenAI()[0]['content']);
    }

    public function testInstanceBuilder(): void
    {
        $engine = new PromptEngine();
        $rendered = $engine->builder()
            ->user('Query: {{q}}')
            ->variables(['q' => 'test'])
            ->render();

        static::assertSame('Query: test', $rendered->asOpenAI()[0]['content']);
    }

    // ── Fluent chaining ───────────────────────────────────────────────────────

    public function testRawToAsPlainString(): void
    {
        $result = PromptEngine::raw("{{product}} photo", ['product' => 'wallet'])->asPlainString();

        static::assertSame('wallet photo', $result);
    }

    public function testChatToAsAnthropic(): void
    {
        $result = PromptEngine::chat([
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Tell me a joke'],
        ])->asAnthropic();

        static::assertArrayHasKey('system', $result);
        static::assertCount(1, $result['messages']);
    }

    public function testRawToAsCompletion(): void
    {
        $result = PromptEngine::raw("Translate: {{text}}", ['text' => 'Hello'])->asCompletion();

        static::assertSame(['prompt' => 'Translate: Hello'], $result);
    }

    public function testRawToAsEmbedding(): void
    {
        $result = PromptEngine::raw("Embed this text")->asEmbedding();

        static::assertSame(['input' => 'Embed this text'], $result);
    }
}
