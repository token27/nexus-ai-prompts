# nexus-ai-prompts

[![CI](https://github.com/token27/nexus-ai-prompts/actions/workflows/ci.yml/badge.svg)](https://github.com/token27/nexus-ai-prompts/actions)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-1f6feb)](https://phpstan.org/)
[![Latest Version](https://img.shields.io/packagist/v/token27/nexus-ai-prompts.svg?style=flat-square)](https://packagist.org/packages/token27/nexus-ai-prompts)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-132%20passing-brightgreen)](#testing)

A **universal, framework-agnostic** PHP 8.3+ prompt engine. Manage complex, multimodal, and weighted prompts as versioned resources. Render to any AI provider (OpenAI, Anthropic, Gemini, Stability AI, etc.) with a single fluent API.

## Why nexus-ai-prompts?

AI payloads are fragmented. OpenAI wants `messages[]`, Anthropic wants `system` + `messages[]`, Gemini wants `parts[]`, and Image/Video models want simple strings.

**nexus-ai-prompts** solves this by:

- **Block-based architecture**: Prompts are collections of flexible blocks, not just chat roles.
- **Provider-agnostic core**: Render once, format for any API (8 built-in formatters).
- **First-class versioning**: Ship `v1.0.0` today, test `v2.0.0` tomorrow, rollback instantly.
- **Multi-language cascade**: Automatic fallback from `es_AR` → `es` → `en`.
- **Fluent API**: Entry points for every workflow — from static one-liners to complex builders.

## Features

- **Universal Prompt Storage**: JSON-based prompts with `meta`, `blocks`, and `variables`.
- **8 Output Formatters**: OpenAI, Anthropic, Gemini, PlainString, Completion, Stability AI, Ollama, Embedding.
- **Mustache Templating**: Full logic support (`{{variable}}`, `{{#section}}`) with strict variable validation.
- **`latest` version resolution**: Always resolve to the highest semantic version automatically.
- **Discovery**: `autoloadFrom()` registers whole libraries; `PromptFinder::scan()` discovers prompts on disk.
- **Zero Config Raw Prompts**: Render dynamic templates on-the-fly via `PromptEngine::raw()`.
- **Type Safety**: PHPStan Level 8, production-grade architecture.

## Installation

```bash
composer require token27/nexus-ai-prompts
```

**Requires:** PHP 8.3+ · `mustache/mustache ^2.14` · `league/flysystem ^3.0`

## Quick Start

### 1. Static One-Liners (No Registry)

```php
use Token27\NexusAI\Prompts\PromptEngine;

// Image/Video Prompt
$str = PromptEngine::raw("A {{animal}} in space", ['animal' => 'cat'])->asPlainString();

// Chat Prompt
$messages = PromptEngine::chat([
    ['role' => 'system', 'content' => 'You are {{persona}}'],
    ['role' => 'user', 'content' => 'Hello!'],
], ['persona' => 'helpful'])->asOpenAI();
```

### 2. Fluent Builder

```php
$payload = PromptEngine::build()
    ->system('You are {{persona}}')
    ->user('Explain {{topic}}')
    ->variables(['persona' => 'teacher', 'topic' => 'Quantum Physics'])
    ->render()
    ->asAnthropic(); // returns ['system' => '...', 'messages' => [...]]
```

### 3. Versioned Registry (JSON Files)

**File: `resources/prompts/article/research/v1.0.0/en.json`**

```json
{
    "meta": { "version": "1.0.0", "prompt_type": "research", "language": "en" },
    "blocks": [
        { "role": "system", "content": "Expert researcher in {{field}}." },
        { "role": "user",   "content": "Topic: {{topic}}" }
    ],
    "variables": {
        "field": { "type": "string", "required": false, "default": "AI" },
        "topic": { "type": "string", "required": true }
    }
}
```

**Usage:**

```php
$registry->autoloadFrom(__DIR__ . '/..');

$payload = $registry->resolve('article/research')
    ->render(['topic' => 'PHP 8.4'])
    ->asGemini(); // returns ['contents' => [['role' => 'user', 'parts' => [...]]]]
```

## Documentation

- [Prompt Format](docs/prompt-format.md) — The new `blocks` schema
- [Fluent API (Engine & Builder)](docs/fluent-api.md) — Create prompts on-the-fly
- [Output Formatters](docs/output-formats.md) — OpenAI, Anthropic, Gemini, etc.
- [Registry & Resolution](docs/registry.md) — Loading versioned prompts
- [Language Fallback](docs/language-fallback.md) — How the cascade works
- [Multi-Source](docs/multi-source.md) — handling name collisions
- [PromptFinder](docs/prompt-finder.md) — zero-config discovery
- [Advanced Integration](docs/advanced-integration.md) — Ecosystem usage and Custom formatters
- [Architecture](docs/architecture.md) — How the blocks system works
- [Internal Logic & Diagrams](docs/internals.md) — Sequence diagrams and deep-dives
- [Testing](docs/testing.md) — How to test your prompts
- [Troubleshooting](docs/troubleshooting.md) — Exceptions, errors and common solutions

## License

MIT. See [LICENSE](LICENSE).
