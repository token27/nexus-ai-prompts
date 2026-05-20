<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\ValueObject;

final readonly class VariableDef
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public mixed $default = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $name, array $data): self
    {
        return new self(
            name: $name,
            type: (string) ($data['type'] ?? 'string'),
            required: (bool) ($data['required'] ?? false),
            default: $data['default'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'required' => $this->required,
            'default' => $this->default,
        ];
    }
}
