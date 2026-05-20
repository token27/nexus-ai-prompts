<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\ValueObject;

use function is_array;

final readonly class PromptMetadata
{
    /**
     * @param list<string> $modelHints
     * @param list<string> $tags
     */
    public function __construct(
        public string $version,
        public string $promptType,
        public string $language,
        public ?string $createdAt = null,
        public ?float $costEstimated = null,
        public array $modelHints = [],
        public ?string $category = null,
        public array $tags = [],
        public mixed $outputSchema = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (string) ($data['version'] ?? ''),
            promptType: (string) ($data['prompt_type'] ?? ''),
            language: (string) ($data['language'] ?? ''),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            costEstimated: isset($data['cost_estimated']) ? (float) $data['cost_estimated'] : null,
            modelHints: is_array($data['model_hints'] ?? null) ? array_values(array_map('strval', $data['model_hints'])) : [],
            category: isset($data['category']) ? (string) $data['category'] : null,
            tags: is_array($data['tags'] ?? null) ? array_values(array_map('strval', $data['tags'])) : [],
            outputSchema: $data['output_schema'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'prompt_type' => $this->promptType,
            'language' => $this->language,
            'created_at' => $this->createdAt,
            'cost_estimated' => $this->costEstimated,
            'model_hints' => $this->modelHints,
            'category' => $this->category,
            'tags' => $this->tags,
            'output_schema' => $this->outputSchema,
        ];
    }
}
