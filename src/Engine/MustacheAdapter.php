<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Engine;

use Mustache_Engine;
use Token27\NexusAI\Prompts\Contract\TemplateEngineInterface;

final class MustacheAdapter implements TemplateEngineInterface
{
    private Mustache_Engine $engine;

    /** @var array<string, callable> */
    private array $helpers = [];

    public function __construct(?Mustache_Engine $engine = null)
    {
        $this->engine = $engine ?? new Mustache_Engine([
            'pragmas' => [Mustache_Engine::PRAGMA_FILTERS],
            'strict_callables' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $template, array $variables): string
    {
        $context = array_merge($this->helpers, $variables);

        return $this->engine->render($template, $context);
    }

    public function supportsHelpers(): bool
    {
        return true;
    }

    public function registerHelper(string $name, callable $helper): void
    {
        $this->helpers[$name] = $helper;
    }
}
