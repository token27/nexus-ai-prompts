# Registry & Resolution

## Overview

`PromptRegistry` is the central hub for managing versioned prompts across multiple sources.

- **Registered directories** — Logical namespaces discovered at runtime.
- **Storages** — Support for Local Filesystem or Flysystem.
- **In-memory cache** — Blazing fast resolution after the first hit.
- **Manual registration** — Add dynamic prompts via `register()`.

## Resolving a Prompt

### Full explicit call

```php
$prompt = $registry->resolve(
    identifier: 'article/research',
    version:    '1.0.0',
    language:   'es',
    source:     'token27/nexus-ai-prompts-articles',
);
```

### Minimal call (All Defaults)

```php
// Uses defaultVersion ('latest'), defaultLanguage, and auto-source detection
$prompt = $registry->resolve('article/research');
```

If the identifier is found in exactly one source, it resolves automatically. If found in multiple sources, an `AmbiguousPromptException` is thrown. See the [Troubleshooting](troubleshooting.md) guide for help resolving this.

### Using `latest`

The `latest` version (default) always resolves to the highest semantic version available in the storage for that specific language.

## Dynamic Defaults

You can change the registry's behavior at runtime:

```php
$registry->setDefaultLanguage('es');
$registry->setDefaultVersion('1.0.0');

// Resolutions now use these values when parameters are null
$prompt = $registry->resolve('article/research'); 
```

The easiest way to register your prompts is to point the registry to your library:

```php
// Scans resources/prompts/ and registers every namespace found.
// Reads the source name (vendor/package) from composer.json.
$registry->autoloadFrom(__DIR__ . '/..');
```

By default, the registry determines the source name by reading `composer.json` at the root, or falling back to the directory's basename. However, you can explicitly set the source name:

```php
$registry->autoloadFrom(__DIR__ . '/..', 'my-custom-source-name');
```

For more complex ecosystem loading processes, check [Advanced Integration](advanced-integration.md).

## Rendering & Formatting

Resolving a prompt returns a `Prompt` object. Calling `render()` on it returns a `RenderedPrompt` object, which provides the fluent formatting API.

```php
$rendered = $registry->resolve('article/research')
    ->render(['topic' => 'AI']);

// Terminal methods (Output Formatters)
$payload = $rendered->asOpenAI();      // [{role, content}]
$payload = $rendered->asAnthropic();   // {system, messages}
$payload = $rendered->asGemini();      // {contents: [{role, parts}]}
$payload = $rendered->asPlainString(); // string
```

See [Prompt Format](prompt-format.md) for more on the `blocks` architecture.
