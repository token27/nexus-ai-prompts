# nexus-ai-prompts — Documentation

Welcome to the full documentation for `token27/nexus-ai-prompts` v1.0.0.

## Table of Contents

### Getting Started

| Guide | Description |
|-------|-------------|
| [Installation & Setup](installation.md) | Composer, requirements, first registry instance |
| [Prompt Format](prompt-format.md) | The new **blocks** JSON schema and field reference |
| [Fluent API](fluent-api.md) | Creating prompts on-the-fly with `Engine` and `Builder` |
| [Output Formatters](output-formats.md) | The 8 ways to format payloads (OpenAI, Anthropic, Gemini, etc.) |

### Core Concepts

| Guide | Description |
|-------|-------------|
| [Registry & Resolution](registry.md) | How `PromptRegistry` resolves prompts, cache, and API |
| [Mustache Templating](templating.md) | Variables, sections, inverted sections, and helpers |
| [Language Fallback](language-fallback.md) | Cascade strategy: `es_AR → es → en` |
| [Multi-Source Discovery](prompt-finder.md) | Auto-discovering prompts across packages and vendors |

### Advanced Usage

| Guide | Description |
|-------|-------------|
| [Multi-Source Collision](multi-source.md) | Handling name collisions with explicit source resolution |
| [Advanced Integration](advanced-integration.md) | Ecosystem integration, dynamic registry building, and custom formatting |
| [Storage Backends](storage.md) | `LocalFilesystemStorage` vs `FlysystemStorage` (S3/GCS) |
| [Architecture](architecture.md) | Component diagram, data flow, and design decisions |
| [Internal Logic & Diagrams](internals.md) | Sequence diagrams and deep-dive logic flows |
| [Troubleshooting](troubleshooting.md) | Common errors, Exceptions, and solutions |

### Development & DevOps

| Guide | Description |
|-------|-------------|
| [Testing](testing.md) | Running the test suite and writing prompt fixtures |
| [Contributing](contributing.md) | Development setup, standards, and adding new formatters |

## At a Glance

```
nexus-ai-prompts/
  src/
    Contract/          # Interfaces (PromptInterface, OutputFormatterInterface, etc.)
    ValueObject/       # Immutable DTOs (PromptMetadata, VariableDef, RenderedPrompt)
    Exception/         # Custom exceptions
    Storage/           # LocalFilesystemStorage + FlysystemStorage
    Engine/            # MustacheAdapter
    Loader/            # PromptLoader + PromptSchemaValidator
    Formatter/         # 8 built-in Output Formatters
    Discovery/         # PromptFinder
    Prompt.php         # Core Prompt object
    PromptRegistry.php # Central registry
    PromptEngine.php   # Static factory entry point
    PromptBuilder.php  # Fluent builder
```

## Ecosystem Position

`nexus-ai-prompts` is the foundational prompt engine for the nexus-ai ecosystem. It is designed to be used by higher-level packages such as `nexus-ai-prompts-articles` and `nexus-ai-prompts-social`, which register their prompts into a shared registry.
