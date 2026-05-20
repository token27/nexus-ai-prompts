<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Contract;

use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

interface PromptInterface
{
    public function getIdentifier(): string;

    public function getVersion(): string;

    public function getLanguage(): string;

    public function getSource(): string;

    public function getMetadata(): PromptMetadata;

    /**
     * @return array<string, \Token27\NexusAI\Prompts\ValueObject\VariableDef>
     */
    public function getVariableDefs(): array;

    /**
     * Get the raw (unrendered) content blocks.
     *
     * Each block is an associative array where 'content' is the only
     * universally expected key. Additional keys (role, name, weight, etc.)
     * are preserved but never required.
     *
     * @return list<array<string, mixed>>
     */
    public function getBlocks(): array;

    /**
     * @param array<string, mixed> $variables
     */
    public function render(array $variables): RenderedPrompt;
}
