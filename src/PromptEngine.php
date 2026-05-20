<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts;

use function is_array;
use function is_string;

use Token27\NexusAI\Prompts\Contract\TemplateEngineInterface;
use Token27\NexusAI\Prompts\Engine\MustacheAdapter;
use Token27\NexusAI\Prompts\ValueObject\PromptMetadata;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

/**
 * Lightweight engine for rendering prompts on-the-fly without JSON files.
 *
 * Three entry points:
 *
 * 1. Static one-liner (raw string):
 *    PromptEngine::raw("A {{animal}} in space", ['animal' => 'cat'])->asPlainString()
 *
 * 2. Static one-liner (chat blocks):
 *    PromptEngine::chat([['role' => 'system', 'content' => '...']], $vars)->asOpenAI()
 *
 * 3. Fluent builder:
 *    PromptEngine::build()->system('...')->user('...')->variables($vars)->render()->asAnthropic()
 *
 * All three return RenderedPrompt — no format is ever assumed.
 */
final class PromptEngine
{
    private TemplateEngineInterface $engine;

    public function __construct(?TemplateEngineInterface $engine = null)
    {
        $this->engine = $engine ?? new MustacheAdapter();
    }

    // =========================================================================
    // Static Factories (zero-config, use default MustacheAdapter)
    // =========================================================================

    /**
     * Render a raw string template on-the-fly.
     *
     * @param string               $template  Template with {{variables}}
     * @param array<string, mixed> $variables Values to interpolate
     */
    public static function raw(string $template, array $variables = []): RenderedPrompt
    {
        return (new self())->renderRaw($template, $variables);
    }

    /**
     * Render an array of chat-style blocks on-the-fly.
     *
     * @param list<array<string, mixed>> $blocks    Blocks with role+content
     * @param array<string, mixed>       $variables Values to interpolate
     */
    public static function chat(array $blocks, array $variables = []): RenderedPrompt
    {
        return (new self())->renderChat($blocks, $variables);
    }

    /**
     * Start a fluent PromptBuilder with default engine.
     */
    public static function build(): PromptBuilder
    {
        return new PromptBuilder(new MustacheAdapter());
    }

    // =========================================================================
    // Instance Methods (for injected custom engine)
    // =========================================================================

    /**
     * Render a raw string template using the injected engine.
     *
     * @param string               $template  Template with {{variables}}
     * @param array<string, mixed> $variables Values to interpolate
     */
    public function renderRaw(string $template, array $variables = []): RenderedPrompt
    {
        $content = $this->engine->render($template, $variables);

        return new RenderedPrompt(
            blocks: [['content' => $content]],
            metadata: new PromptMetadata(
                version: '0.0.0',
                promptType: 'raw',
                language: 'en',
            ),
            language: 'en',
            version: '0.0.0',
            source: 'runtime',
        );
    }

    /**
     * Render an array of blocks using the injected engine.
     *
     * @param list<array<string, mixed>> $blocks    Blocks (role optional)
     * @param array<string, mixed>       $variables Values to interpolate
     */
    public function renderChat(array $blocks, array $variables = []): RenderedPrompt
    {
        $rendered = [];

        foreach ($blocks as $block) {
            $rendered[] = $this->renderBlock($block, $variables);
        }

        $hasRoles = !empty($rendered) && isset($rendered[0]['role']);

        return new RenderedPrompt(
            blocks: $rendered,
            metadata: new PromptMetadata(
                version: '0.0.0',
                promptType: $hasRoles ? 'chat' : 'raw',
                language: 'en',
            ),
            language: 'en',
            version: '0.0.0',
            source: 'runtime',
        );
    }

    /**
     * Start a fluent PromptBuilder with the injected engine.
     */
    public function builder(): PromptBuilder
    {
        return new PromptBuilder($this->engine);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Render a single block — supports string content AND multimodal arrays.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function renderBlock(array $block, array $variables): array
    {
        $rendered = $block;

        if (isset($rendered['content'])) {
            if (is_string($rendered['content'])) {
                $rendered['content'] = $this->engine->render($rendered['content'], $variables);
            } elseif (is_array($rendered['content'])) {
                $rendered['content'] = array_map(function (mixed $part) use ($variables): mixed {
                    if (is_array($part)) {
                        if (isset($part['text']) && is_string($part['text'])) {
                            $part['text'] = $this->engine->render($part['text'], $variables);
                        }

                        if (isset($part['image_url']['url']) && is_string($part['image_url']['url'])) {
                            $part['image_url']['url'] = $this->engine->render($part['image_url']['url'], $variables);
                        }
                    }

                    return $part;
                }, $rendered['content']);
            }
        }

        return $rendered;
    }
}
