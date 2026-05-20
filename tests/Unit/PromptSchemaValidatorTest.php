<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Prompts\Exception\InvalidPromptSchemaException;
use Token27\NexusAI\Prompts\Loader\PromptSchemaValidator;

final class PromptSchemaValidatorTest extends TestCase
{
    private PromptSchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PromptSchemaValidator();
    }

    public function testValidSchemaWithBlocksKeyPassesValidation(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate($this->validDataWithBlocks());
    }

    public function testBlocksWithoutRolePassesValidation(): void
    {
        // roles are OPTIONAL in the new schema
        $data = $this->validDataWithBlocks();
        unset($data['blocks'][0]['role']);

        $this->expectNotToPerformAssertions();

        $this->validator->validate($data);
    }

    // ── Meta validation ───────────────────────────────────────────────────────

    public function testMissingMetaThrowsException(): void
    {
        $data = $this->validDataWithBlocks();
        unset($data['meta']);

        $this->expectException(InvalidPromptSchemaException::class);
        $this->expectExceptionMessage('meta');

        $this->validator->validate($data);
    }

    public function testMissingMetaVersionThrowsException(): void
    {
        $data = $this->validDataWithBlocks();
        unset($data['meta']['version']);

        $this->expectException(InvalidPromptSchemaException::class);
        $this->expectExceptionMessage('meta.version');

        $this->validator->validate($data);
    }

    // ── Blocks validation ─────────────────────────────────────────────────────

    public function testMissingBlocksThrowsException(): void
    {
        $data = $this->validDataWithBlocks();
        unset($data['blocks']);

        $this->expectException(InvalidPromptSchemaException::class);
        $this->expectExceptionMessage('blocks');

        $this->validator->validate($data);
    }

    public function testEmptyBlocksThrowsException(): void
    {
        $data = $this->validDataWithBlocks();
        $data['blocks'] = [];

        $this->expectException(InvalidPromptSchemaException::class);

        $this->validator->validate($data);
    }

    public function testBlockMissingContentThrowsException(): void
    {
        $data = $this->validDataWithBlocks();
        unset($data['blocks'][0]['content']);

        $this->expectException(InvalidPromptSchemaException::class);
        $this->expectExceptionMessage('content');

        $this->validator->validate($data);
    }

    // ── Variables validation ──────────────────────────────────────────────────

    public function testMissingVariablesThrowsException(): void
    {
        $data = $this->validDataWithBlocks();
        unset($data['variables']);

        $this->expectException(InvalidPromptSchemaException::class);
        $this->expectExceptionMessage('variables');

        $this->validator->validate($data);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validDataWithBlocks(): array
    {
        return [
            'meta' => [
                'version' => '1.0.0',
                'prompt_type' => 'test',
                'language' => 'en',
            ],
            'blocks' => [
                ['role' => 'system', 'content' => 'You are a test assistant.'],
                ['role' => 'user', 'content' => 'Hello {{name}}'],
            ],
            'variables' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ];
    }
}
