<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

final class RenderedPromptTest extends TestCase
{
    private RenderedPrompt $chatPrompt;
    private RenderedPrompt $plainPrompt;
    private RenderedPrompt $weightedPrompt;

    protected function setUp(): void
    {
        $meta = new PromptMetadata(version: '1.0.0', promptType: 'chat', language: 'en');

        $this->chatPrompt = new RenderedPrompt(
            blocks: [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
            metadata: $meta,
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );

        $this->plainPrompt = new RenderedPrompt(
            blocks: [
                ['content' => 'A cat in space, 8k'],
            ],
            metadata: new PromptMetadata(version: '1.0.0', promptType: 'image', language: 'en'),
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );

        $this->weightedPrompt = new RenderedPrompt(
            blocks: [
                ['content' => 'sunset mountains, golden hour', 'weight' => 1.0],
                ['content' => 'blurry, watermark', 'weight' => -0.8],
            ],
            metadata: new PromptMetadata(version: '1.0.0', promptType: 'image-weighted', language: 'en'),
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );
    }

    // ── asOpenAI() ────────────────────────────────────────────────────────────

    public function testAsOpenAIReturnsChatMessages(): void
    {
        $result = $this->chatPrompt->asOpenAI();

        static::assertCount(2, $result);
        static::assertSame('system', $result[0]['role']);
        static::assertSame('You are a helpful assistant.', $result[0]['content']);
        static::assertSame('user', $result[1]['role']);
        static::assertSame('Hello!', $result[1]['content']);
    }

    public function testAsOpenAIThrowsWhenNoRoles(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('asOpenAI');

        $this->plainPrompt->asOpenAI();
    }

    // ── asAnthropic() ─────────────────────────────────────────────────────────

    public function testAsAnthropicSeparatesSystemFromMessages(): void
    {
        $result = $this->chatPrompt->asAnthropic();

        static::assertArrayHasKey('system', $result);
        static::assertSame('You are a helpful assistant.', $result['system']);
        static::assertCount(1, $result['messages']);
        static::assertSame('user', $result['messages'][0]['role']);
    }

    public function testAsAnthropicWithoutSystemHasNoSystemKey(): void
    {
        $prompt = new RenderedPrompt(
            blocks: [['role' => 'user', 'content' => 'Hello!']],
            metadata: new PromptMetadata(version: '1.0.0', promptType: 'chat', language: 'en'),
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );

        $result = $prompt->asAnthropic();

        static::assertArrayNotHasKey('system', $result);
        static::assertCount(1, $result['messages']);
    }

    // ── asGemini() ────────────────────────────────────────────────────────────

    public function testAsGeminiReturnsContentsWithParts(): void
    {
        $result = $this->chatPrompt->asGemini();

        static::assertArrayHasKey('contents', $result);
        static::assertCount(2, $result['contents']);
        static::assertSame('user', $result['contents'][0]['role']); // system maps to user
        static::assertSame([['text' => 'You are a helpful assistant.']], $result['contents'][0]['parts']);
    }

    public function testAsGeminiMapsAssistantToModel(): void
    {
        $prompt = new RenderedPrompt(
            blocks: [['role' => 'assistant', 'content' => 'I can help!']],
            metadata: new PromptMetadata(version: '1.0.0', promptType: 'chat', language: 'en'),
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );

        $result = $prompt->asGemini();

        static::assertSame('model', $result['contents'][0]['role']);
    }

    // ── asPlainString() ───────────────────────────────────────────────────────

    public function testAsPlainStringConcatenatesContent(): void
    {
        static::assertSame('A cat in space, 8k', $this->plainPrompt->asPlainString());
    }

    public function testAsPlainStringWithCustomSeparator(): void
    {
        $prompt = new RenderedPrompt(
            blocks: [['content' => 'Line 1'], ['content' => 'Line 2']],
            metadata: new PromptMetadata(version: '1.0.0', promptType: 'raw', language: 'en'),
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );

        static::assertSame('Line 1 | Line 2', $prompt->asPlainString(' | '));
    }

    public function testAsPlainStringWorksOnChatPrompt(): void
    {
        // Should concatenate all contents regardless of roles
        $result = $this->chatPrompt->asPlainString(' ');

        static::assertStringContainsString('You are a helpful assistant.', $result);
        static::assertStringContainsString('Hello!', $result);
    }

    // ── asCompletion() ────────────────────────────────────────────────────────

    public function testAsCompletionReturnsPromptKey(): void
    {
        $result = $this->plainPrompt->asCompletion();

        static::assertArrayHasKey('prompt', $result);
        static::assertSame('A cat in space, 8k', $result['prompt']);
    }

    // ── asStabilityAI() ───────────────────────────────────────────────────────

    public function testAsStabilityAIReturnsTextPromptsWithWeights(): void
    {
        $result = $this->weightedPrompt->asStabilityAI();

        static::assertArrayHasKey('text_prompts', $result);
        static::assertCount(2, $result['text_prompts']);
        static::assertSame(1.0, $result['text_prompts'][0]['weight']);
        static::assertSame(-0.8, $result['text_prompts'][1]['weight']);
    }

    public function testAsStabilityAIDefaultsWeightToOne(): void
    {
        $result = $this->plainPrompt->asStabilityAI();

        static::assertSame(1.0, $result['text_prompts'][0]['weight']);
    }

    // ── asOllama() ────────────────────────────────────────────────────────────

    public function testAsOllamaReturnsMessages(): void
    {
        $result = $this->chatPrompt->asOllama();

        static::assertArrayHasKey('messages', $result);
        static::assertCount(2, $result['messages']);
        static::assertSame('system', $result['messages'][0]['role']);
    }

    public function testAsOllamaThrowsWhenNoRoles(): void
    {
        $this->expectException(RuntimeException::class);

        $this->plainPrompt->asOllama();
    }

    // ── asEmbedding() ─────────────────────────────────────────────────────────

    public function testAsEmbeddingSingleBlock(): void
    {
        $result = $this->plainPrompt->asEmbedding();

        static::assertSame(['input' => 'A cat in space, 8k'], $result);
    }

    public function testAsEmbeddingMultipleBlocksReturnsArray(): void
    {
        $prompt = new RenderedPrompt(
            blocks: [['content' => 'Text A'], ['content' => 'Text B']],
            metadata: new PromptMetadata(version: '1.0.0', promptType: 'raw', language: 'en'),
            language: 'en',
            version: '1.0.0',
            source: 'test',
        );

        $result = $prompt->asEmbedding();

        static::assertSame(['input' => ['Text A', 'Text B']], $result);
    }

    // ── format() ──────────────────────────────────────────────────────────────

    public function testFormatWithStringAliasOpenai(): void
    {
        $result = $this->chatPrompt->format('openai');

        static::assertIsArray($result);
        static::assertSame('system', $result[0]['role']);
    }

    public function testFormatWithStringAliasPlain(): void
    {
        $result = $this->plainPrompt->format('plain');

        static::assertSame('A cat in space, 8k', $result);
    }

    public function testFormatWithUnknownAliasThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown-format');

        $this->plainPrompt->format('unknown-format');
    }

    // ── hasRoles() ────────────────────────────────────────────────────────────

    public function testHasRolesTrueForChatPrompt(): void
    {
        static::assertTrue($this->chatPrompt->hasRoles());
    }

    public function testHasRolesFalseForPlainPrompt(): void
    {
        static::assertFalse($this->plainPrompt->hasRoles());
    }

    // ── Convenience accessors ─────────────────────────────────────────────────

    public function testGetSystemMessage(): void
    {
        static::assertSame('You are a helpful assistant.', $this->chatPrompt->getSystemMessage());
    }

    public function testGetUserMessage(): void
    {
        static::assertSame('Hello!', $this->chatPrompt->getUserMessage());
    }

    public function testGetSystemMessageReturnsNullWhenMissing(): void
    {
        static::assertNull($this->plainPrompt->getSystemMessage());
    }

    public function testGetPromptType(): void
    {
        static::assertSame('chat', $this->chatPrompt->getPromptType());
        static::assertSame('image', $this->plainPrompt->getPromptType());
    }

    public function testToArray(): void
    {
        $arr = $this->plainPrompt->toArray();

        static::assertArrayHasKey('blocks', $arr);
        static::assertArrayHasKey('metadata', $arr);
        static::assertArrayHasKey('language', $arr);
        static::assertArrayHasKey('version', $arr);
        static::assertArrayHasKey('source', $arr);
    }
}
