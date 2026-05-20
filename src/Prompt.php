<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts;

use function array_key_exists;
use function is_array;
use function is_string;

use Token27\NexusAI\Prompts\Contract\PromptInterface;
use Token27\NexusAI\Prompts\Contract\TemplateEngineInterface;
use Token27\NexusAI\Prompts\Exception\VariableValidationException;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;
use Token27\NexusAI\Prompts\ValueObject\VariableDef;

final class Prompt implements PromptInterface
{
    /**
     * @param list<array<string, mixed>>   $blocks       Content blocks (role optional)
     * @param array<string, VariableDef>   $variableDefs Variable definitions
     */
    public function __construct(
        private readonly string $identifier,
        private readonly string $version,
        private readonly string $language,
        private readonly string $source,
        private readonly array $blocks,
        private readonly array $variableDefs,
        private readonly PromptMetadata $metadata,
        private readonly TemplateEngineInterface $engine,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getMetadata(): PromptMetadata
    {
        return $this->metadata;
    }

    /**
     * @return array<string, VariableDef>
     */
    public function getVariableDefs(): array
    {
        return $this->variableDefs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Validate required variables, apply defaults, render each block with the template engine.
     *
     * Supports:
     *  - String content: rendered via Mustache
     *  - Array content (multimodal): text parts rendered, image_url/etc. preserved
     *  - Extra keys (role, name, weight, tool_calls...): preserved as-is
     *
     * @param array<string, mixed> $variables
     *
     * @throws VariableValidationException
     */
    public function render(array $variables): RenderedPrompt
    {
        $context = $this->buildContext($variables);

        $renderedBlocks = [];

        foreach ($this->blocks as $block) {
            $renderedBlocks[] = $this->renderBlock($block, $context);
        }

        return new RenderedPrompt(
            blocks: $renderedBlocks,
            metadata: $this->metadata,
            language: $this->language,
            version: $this->version,
            source: $this->source,
        );
    }

    /**
     * Build the rendering context: validate required vars, apply defaults.
     *
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     *
     * @throws VariableValidationException
     */
    private function buildContext(array $variables): array
    {
        $missing = [];

        foreach ($this->variableDefs as $name => $def) {
            if ($def->required && !array_key_exists($name, $variables)) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            throw VariableValidationException::missingRequired($missing);
        }

        // Apply defaults for optional variables not provided
        $context = $variables;

        foreach ($this->variableDefs as $name => $def) {
            if (!array_key_exists($name, $context) && $def->default !== null) {
                $context[$name] = $def->default;
            }
        }

        return $context;
    }

    /**
     * Render a single block — supports string content AND multimodal arrays.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function renderBlock(array $block, array $context): array
    {
        $rendered = $block; // preserve ALL original keys (role, name, weight, etc.)

        if (isset($rendered['content'])) {
            if (is_string($rendered['content'])) {
                // Standard text content — render with Mustache
                $rendered['content'] = $this->engine->render($rendered['content'], $context);
            } elseif (is_array($rendered['content'])) {
                // Multimodal content — render text parts, pass through everything else
                $rendered['content'] = array_map(function (mixed $part) use ($context): mixed {
                    if (is_array($part)) {
                        // Render text fields
                        if (isset($part['text']) && is_string($part['text'])) {
                            $part['text'] = $this->engine->render($part['text'], $context);
                        }
                        // Render image_url.url if it contains variables
                        if (isset($part['image_url']['url']) && is_string($part['image_url']['url'])) {
                            $part['image_url']['url'] = $this->engine->render($part['image_url']['url'], $context);
                        }
                    }

                    return $part;
                }, $rendered['content']);
            }
        }

        return $rendered;
    }
}
