<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Contract;

interface TemplateEngineInterface
{
    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $template, array $variables): string;

    public function supportsHelpers(): bool;

    public function registerHelper(string $name, callable $helper): void;
}
