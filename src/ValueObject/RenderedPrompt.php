<?php

declare(strict_types=1);

namespace Token27\NexusAI\Prompts\ValueObject;

use function count;

use InvalidArgumentException;

use function is_array;
use function is_string;

use RuntimeException;

use function sprintf;

use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;

/**
 * Represents a fully rendered, ready-to-consume prompt.
 *
 * Agnostic container of rendered "blocks". The format is NEVER assumed —
 * the consumer explicitly chooses the output format via terminal methods:
 *
 *   $rendered->asOpenAI()       // [['role' => '...', 'content' => '...']]
 *   $rendered->asAnthropic()    // ['system' => '...', 'messages' => [...]]
 *   $rendered->asGemini()       // ['contents' => [['role' => '...', 'parts' => [...]]]]
 *   $rendered->asPlainString()  // "A cat in space, 8k"
 *   $rendered->asCompletion()   // ['prompt' => '...']
 *   $rendered->asStabilityAI()  // ['text_prompts' => [['text' => '...', 'weight' => 1.0]]]
 *   $rendered->asOllama()       // ['messages' => [...]]
 *   $rendered->asEmbedding()    // ['input' => '...']
 *   $rendered->format('name')   // String alias or custom OutputFormatterInterface
 */
final readonly class RenderedPrompt
{
    /**
     * @param list<array<string, mixed>> $blocks   Rendered content blocks
     */
    public function __construct(
        public array $blocks,
        public PromptMetadata $metadata,
        public string $language,
        public string $version,
        public string $source,
    ) {}

    // =========================================================================
    // Named Format Methods (Terminal — each returns a specific API format)
    // =========================================================================

    /**
     * OpenAI Chat / Mistral / Groq / OpenRouter format.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException When blocks have no roles
     */
    public function asOpenAI(): array
    {
        $this->assertHasRoles('asOpenAI');

        $messages = [];

        foreach ($this->blocks as $block) {
            $msg = [
                'role' => (string) $block['role'],
                'content' => $block['content'] ?? '',
            ];

            // Preserve extra keys: name, tool_calls, tool_call_id, etc.
            foreach ($block as $key => $value) {
                if ($key !== 'role' && $key !== 'content') {
                    $msg[$key] = $value;
                }
            }

            $messages[] = $msg;
        }

        return $messages;
    }

    /**
     * Anthropic Claude format — system message as a top-level key.
     *
     * @return array{system?: string, messages: list<array{role: string, content: string|list<array<string, mixed>>}>}
     *
     * @throws RuntimeException When blocks have no roles
     */
    public function asAnthropic(): array
    {
        $this->assertHasRoles('asAnthropic');

        $system = null;
        $messages = [];

        foreach ($this->blocks as $block) {
            $role = (string) ($block['role'] ?? 'user');

            if ($role === 'system') {
                // Anthropic: system is top-level, not in messages
                $content = $block['content'] ?? '';
                $system = is_string($content) ? $content : '';
            } else {
                $messages[] = [
                    'role' => $role,
                    'content' => $block['content'] ?? '',
                ];
            }
        }

        $payload = ['messages' => $messages];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        return $payload;
    }

    /**
     * Google Gemini format — contents with parts.
     *
     * @return array{contents: list<array{role: string, parts: list<array<string, mixed>>}>}
     *
     * @throws RuntimeException When blocks have no roles
     */
    public function asGemini(): array
    {
        $this->assertHasRoles('asGemini');

        $contents = [];

        foreach ($this->blocks as $block) {
            $role = (string) ($block['role'] ?? 'user');
            $content = $block['content'] ?? '';

            // Gemini uses 'model' instead of 'assistant'
            if ($role === 'assistant') {
                $role = 'model';
            }

            // Gemini: system instructions are usually handled differently,
            // but for basic usage we include them as 'user' role
            if ($role === 'system') {
                $role = 'user';
            }

            // Build parts array
            if (is_string($content)) {
                $parts = [['text' => $content]];
            } elseif (is_array($content)) {
                // Map multimodal content to Gemini parts format
                $parts = [];

                foreach ($content as $item) {
                    if (is_array($item)) {
                        $type = $item['type'] ?? '';

                        if ($type === 'text') {
                            $parts[] = ['text' => (string) ($item['text'] ?? '')];
                        } elseif ($type === 'image_url') {
                            $parts[] = [
                                'inline_data' => [
                                    'mime_type' => 'image/jpeg',
                                    'data' => (string) ($item['image_url']['url'] ?? ''),
                                ],
                            ];
                        } else {
                            $parts[] = $item;
                        }
                    }
                }
            } else {
                $parts = [['text' => (string) $content]];
            }

            $contents[] = [
                'role' => $role,
                'parts' => $parts,
            ];
        }

        return ['contents' => $contents];
    }

    /**
     * Plain string — all block contents concatenated.
     * Perfect for: DALL-E, StableDiffusion, Midjourney, ElevenLabs, RunwayML, Luma, Suno.
     */
    public function asPlainString(string $separator = "\n\n"): string
    {
        $parts = [];

        foreach ($this->blocks as $block) {
            $content = $block['content'] ?? '';

            if (is_array($content)) {
                // Multimodal: extract text parts only
                foreach ($content as $part) {
                    if (is_array($part) && ($part['type'] ?? '') === 'text') {
                        $parts[] = (string) ($part['text'] ?? '');
                    }
                }
            } else {
                $text = (string) $content;

                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return implode($separator, $parts);
    }

    /**
     * Completion/Instruct format — single prompt string.
     * Perfect for: GPT-3 Instruct, Ollama /api/generate, local models.
     *
     * @return array{prompt: string}
     */
    public function asCompletion(): array
    {
        return ['prompt' => $this->asPlainString()];
    }

    /**
     * Stability AI format — weighted text prompts.
     *
     * @return array{text_prompts: list<array{text: string, weight: float}>}
     */
    public function asStabilityAI(): array
    {
        $textPrompts = [];

        foreach ($this->blocks as $block) {
            $content = $block['content'] ?? '';
            $weight = (float) ($block['weight'] ?? 1.0);

            $textPrompts[] = [
                'text' => is_string($content) ? $content : (string) $content,
                'weight' => $weight,
            ];
        }

        return ['text_prompts' => $textPrompts];
    }

    /**
     * Ollama chat endpoint format.
     *
     * @return array{messages: list<array{role: string, content: string}>}
     *
     * @throws RuntimeException When blocks have no roles
     */
    public function asOllama(): array
    {
        $this->assertHasRoles('asOllama');

        $messages = [];

        foreach ($this->blocks as $block) {
            $content = $block['content'] ?? '';

            $messages[] = [
                'role' => (string) ($block['role'] ?? 'user'),
                'content' => is_string($content) ? $content : '',
            ];
        }

        return ['messages' => $messages];
    }

    /**
     * Embedding format — single input string or array.
     *
     * @return array{input: string|list<string>}
     */
    public function asEmbedding(): array
    {
        if (count($this->blocks) === 1) {
            $content = $this->blocks[0]['content'] ?? '';

            return ['input' => is_string($content) ? $content : (string) $content];
        }

        // Multiple blocks → array of inputs
        $inputs = [];

        foreach ($this->blocks as $block) {
            $content = $block['content'] ?? '';
            $inputs[] = is_string($content) ? $content : (string) $content;
        }

        return ['input' => $inputs];
    }

    // =========================================================================
    // Generic Format Method (string alias or custom formatter object)
    // =========================================================================

    /**
     * Format using a string alias or a custom OutputFormatterInterface.
     *
     * String aliases map to built-in as*() methods:
     *   'openai', 'anthropic', 'gemini', 'plain', 'string', 'text',
     *   'completion', 'stability', 'ollama', 'embedding'
     *
     */
    public function format(string|OutputFormatterInterface $formatter): mixed
    {
        if ($formatter instanceof OutputFormatterInterface) {
            return $formatter->format($this);
        }

        return match ($formatter) {
            'openai', 'openai-chat' => $this->asOpenAI(),
            'anthropic', 'claude' => $this->asAnthropic(),
            'gemini', 'google' => $this->asGemini(),
            'plain', 'string', 'text' => $this->asPlainString(),
            'completion', 'instruct' => $this->asCompletion(),
            'stability', 'stability-ai' => $this->asStabilityAI(),
            'ollama' => $this->asOllama(),
            'embedding', 'embed' => $this->asEmbedding(),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unknown format "%s". Available: openai, anthropic, gemini, plain, completion, stability, ollama, embedding.',
                    $formatter,
                ),
            ),
        };
    }

    // =========================================================================
    // Raw Data Access
    // =========================================================================

    /**
     * Get the raw rendered blocks for custom processing.
     *
     * @return list<array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Full serializable representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'blocks' => $this->blocks,
            'metadata' => $this->metadata->toArray(),
            'language' => $this->language,
            'version' => $this->version,
            'source' => $this->source,
        ];
    }

    // =========================================================================
    // Inspection & Convenience
    // =========================================================================

    /**
     * Whether ALL blocks have a 'role' key (i.e., this is a chat-style prompt).
     */
    public function hasRoles(): bool
    {
        if ($this->blocks === []) {
            return false;
        }

        foreach ($this->blocks as $block) {
            if (!isset($block['role'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the prompt_type from metadata.
     */
    public function getPromptType(): string
    {
        return $this->metadata->promptType;
    }

    /**
     * Get the content of the first block matching a specific role.
     */
    public function getBlockContent(string $role): ?string
    {
        foreach ($this->blocks as $block) {
            if (($block['role'] ?? null) === $role) {
                $content = $block['content'] ?? null;

                return is_string($content) ? $content : null;
            }
        }

        return null;
    }

    /**
     * Shortcut: get system message content.
     */
    public function getSystemMessage(): ?string
    {
        return $this->getBlockContent('system');
    }

    /**
     * Shortcut: get user message content.
     */
    public function getUserMessage(): ?string
    {
        return $this->getBlockContent('user');
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Assert that all blocks have roles. Throws if not.
     *
     * @throws RuntimeException
     */
    private function assertHasRoles(string $method): void
    {
        if (!$this->hasRoles()) {
            throw new RuntimeException(sprintf(
                'Cannot use %s() — one or more blocks have no "role" key. '
                . 'This prompt is not chat-formatted. Use asPlainString(), asCompletion(), or asStabilityAI() instead.',
                $method,
            ));
        }
    }
}
