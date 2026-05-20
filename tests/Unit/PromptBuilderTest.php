<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Token27\NexusAI\Prompts\PromptBuilder;
use Token27\NexusAI\Prompts\PromptEngine;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

final class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = PromptEngine::build();
    }

    // ── Block methods ─────────────────────────────────────────────────────────

    public function testSystemAddsRoleBlock(): void
    {
        $rendered = $this->builder
            ->system('You are helpful')
            ->render();

        $blocks = $rendered->getBlocks();

        static::assertCount(1, $blocks);
        static::assertSame('system', $blocks[0]['role']);
        static::assertSame('You are helpful', $blocks[0]['content']);
    }

    public function testUserAddsRoleBlock(): void
    {
        $rendered = $this->builder
            ->user('Hello')
            ->render();

        static::assertSame('user', $rendered->getBlocks()[0]['role']);
    }

    public function testAssistantAddsRoleBlock(): void
    {
        $rendered = $this->builder
            ->assistant('I will help')
            ->render();

        static::assertSame('assistant', $rendered->getBlocks()[0]['role']);
    }

    public function testTextAddsBlockWithoutRole(): void
    {
        $rendered = $this->builder
            ->text('A cat in space')
            ->render();

        $blocks = $rendered->getBlocks();

        static::assertArrayNotHasKey('role', $blocks[0]);
        static::assertSame('A cat in space', $blocks[0]['content']);
    }

    public function testBlockAddsArbitraryBlock(): void
    {
        $rendered = $this->builder
            ->block(['content' => 'sunset', 'weight' => 1.0])
            ->render();

        $blocks = $rendered->getBlocks();

        static::assertSame(1.0, $blocks[0]['weight']);
    }

    // ── Variables ────────────────────────────────────────────────────────────

    public function testVariablesInterpolated(): void
    {
        $rendered = $this->builder
            ->system('You are {{persona}}')
            ->user('{{task}}')
            ->variables(['persona' => 'an expert', 'task' => 'review code'])
            ->render();

        $messages = $rendered->asOpenAI();

        static::assertSame('You are an expert', $messages[0]['content']);
        static::assertSame('review code', $messages[1]['content']);
    }

    public function testVariablesMergeOnMultipleCalls(): void
    {
        $rendered = $this->builder
            ->text('{{a}} {{b}}')
            ->variables(['a' => 'Hello'])
            ->variables(['b' => 'World'])
            ->render();

        static::assertSame('Hello World', $rendered->asPlainString());
    }

    // ── Fluent chaining ───────────────────────────────────────────────────────

    public function testFullChatChainToOpenAI(): void
    {
        $result = PromptEngine::build()
            ->system('You are {{persona}}')
            ->user('Help me with {{task}}')
            ->variables(['persona' => 'an architect', 'task' => 'design'])
            ->render()
            ->asOpenAI();

        static::assertCount(2, $result);
        static::assertSame('You are an architect', $result[0]['content']);
    }

    public function testFullChatChainToAnthropic(): void
    {
        $result = PromptEngine::build()
            ->system('Translate to {{lang}}')
            ->user('{{text}}')
            ->variables(['lang' => 'French', 'text' => 'Hello'])
            ->render()
            ->asAnthropic();

        static::assertSame('Translate to French', $result['system']);
        static::assertSame('Hello', $result['messages'][0]['content']);
    }

    public function testWeightedBlockToStabilityAI(): void
    {
        $result = PromptEngine::build()
            ->block(['content' => '{{subject}}, golden hour', 'weight' => 1.0])
            ->block(['content' => 'blurry, low quality', 'weight' => -0.8])
            ->variables(['subject' => 'mountain landscape'])
            ->render()
            ->asStabilityAI();

        static::assertCount(2, $result['text_prompts']);
        static::assertSame('mountain landscape, golden hour', $result['text_prompts'][0]['text']);
        static::assertSame(-0.8, $result['text_prompts'][1]['weight']);
    }

    public function testPlainTextToCompletion(): void
    {
        $result = PromptEngine::build()
            ->text('Translate: {{text}}')
            ->variables(['text' => 'Hello world'])
            ->render()
            ->asCompletion();

        static::assertSame(['prompt' => 'Translate: Hello world'], $result);
    }

    // ── Error cases ───────────────────────────────────────────────────────────

    public function testRenderWithNoBlocksThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no blocks');

        PromptEngine::build()->render();
    }

    // ── RenderedPrompt inspection ─────────────────────────────────────────────

    public function testRenderReturnsRenderedPrompt(): void
    {
        $rendered = $this->builder->user('Hello')->render();

        static::assertInstanceOf(RenderedPrompt::class, $rendered);
    }

    public function testChatBuilderHasRoles(): void
    {
        $rendered = $this->builder->system('Sys')->user('User')->render();

        static::assertTrue($rendered->hasRoles());
    }

    public function testTextBuilderHasNoRoles(): void
    {
        $rendered = $this->builder->text('A photo')->render();

        static::assertFalse($rendered->hasRoles());
    }
}
