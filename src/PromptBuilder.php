<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts;

use function is_array;
use function is_string;

use RuntimeException;
use Token27\NexusAI\Prompts\Contract\TemplateEngineInterface;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Fluent builder for constructing prompts step by step without JSON files.
 *
 * Usage:
 *   PromptEngine::build()
 *       ->system('You are {{persona}}')
 *       ->user('Help me with {{task}}')
 *       ->variables(['persona' => 'an architect', 'task' => 'design'])
 *       ->render()
 *       ->asOpenAI();
 */
final class PromptBuilder
{
    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    /** @var array<string, mixed> */
    private array $variables = [];

    private ?PromptMetadata $metadata = null;

    public function __construct(
        private readonly TemplateEngineInterface $engine,
    ) {}

    // =========================================================================
    // Block Definition Methods (chainable)
    // =========================================================================

    /**
     * Add a system message block.
     */
    public function system(string $content): self
    {
        $this->blocks[] = ['role' => 'system', 'content' => $content];

        return $this;
    }

    /**
     * Add a user message block.
     */
    public function user(string $content): self
    {
        $this->blocks[] = ['role' => 'user', 'content' => $content];

        return $this;
    }

    /**
     * Add an assistant message block.
     */
    public function assistant(string $content): self
    {
        $this->blocks[] = ['role' => 'assistant', 'content' => $content];

        return $this;
    }

    /**
     * Add a plain text block (no role).
     * Use for image prompts, completion prompts, audio prompts, etc.
     */
    public function text(string $content): self
    {
        $this->blocks[] = ['content' => $content];

        return $this;
    }

    /**
     * Add a generic block with any structure.
     * Use for weighted prompts, multimodal content, custom fields, etc.
     *
     * @param array<string, mixed> $block
     */
    public function block(array $block): self
    {
        $this->blocks[] = $block;

        return $this;
    }

    // =========================================================================
    // Configuration Methods (chainable)
    // =========================================================================

    /**
     * Set variables for Mustache interpolation.
     *
     * @param array<string, mixed> $variables
     */
    public function variables(array $variables): self
    {
        $this->variables = array_merge($this->variables, $variables);

        return $this;
    }

    /**
     * Set custom metadata.
     */
    public function metadata(PromptMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    // =========================================================================
    // Terminal Method — Render
    // =========================================================================

    /**
     * Render all blocks with the configured variables and return a RenderedPrompt.
     *
     * From here, chain any format method:
     *   ->render()->asOpenAI()
     *   ->render()->asPlainString()
     *   ->render()->format('anthropic')
     */
    public function render(): RenderedPrompt
    {
        if ($this->blocks === []) {
            throw new RuntimeException('Cannot render: no blocks have been added. Use ->system(), ->user(), ->text(), or ->block() first.');
        }

        $rendered = [];

        foreach ($this->blocks as $block) {
            $rendered[] = $this->renderBlock($block);
        }

        $hasRoles = isset($rendered[0]['role']);
        $metadata = $this->metadata ?? new PromptMetadata(
            version: '0.0.0',
            promptType: $hasRoles ? 'chat' : 'raw',
            language: 'en',
        );

        return new RenderedPrompt(
            blocks: $rendered,
            metadata: $metadata,
            language: $metadata->language,
            version: $metadata->version,
            source: 'runtime',
        );
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function renderBlock(array $block): array
    {
        $rendered = $block;

        if (isset($rendered['content'])) {
            if (is_string($rendered['content'])) {
                $rendered['content'] = $this->engine->render($rendered['content'], $this->variables);
            } elseif (is_array($rendered['content'])) {
                $rendered['content'] = array_map(function (mixed $part): mixed {
                    if (is_array($part)) {
                        if (isset($part['text']) && is_string($part['text'])) {
                            $part['text'] = $this->engine->render($part['text'], $this->variables);
                        }

                        if (isset($part['image_url']['url']) && is_string($part['image_url']['url'])) {
                            $part['image_url']['url'] = $this->engine->render($part['image_url']['url'], $this->variables);
                        }
                    }

                    return $part;
                }, $rendered['content']);
            }
        }

        return $rendered;
    }
}
