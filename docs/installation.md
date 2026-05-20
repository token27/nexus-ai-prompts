# Installation & Setup

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.3 or higher |
| `mustache/mustache` | `^2.14` |
| `league/flysystem` | `^3.0` |

## Composer Install

```bash
composer require token27/nexus-ai-prompts
```

## Creating a Registry

The entry point is `PromptRegistry`. You instantiate it once (typically in a service container) and inject it wherever prompts are needed.

```php
use Token27\NexusAI\Prompts\PromptRegistry;
use Token27\NexusAI\Prompts\Loader\PromptLoader;
use Token27\NexusAI\Prompts\Loader\PromptSchemaValidator;
use Token27\NexusAI\Prompts\Engine\MustacheAdapter;
use Token27\NexusAI\Prompts\Storage\LocalFilesystemStorage;

$registry = new PromptRegistry(
    loader: new PromptLoader(
        validator: new PromptSchemaValidator(),
        engine: new MustacheAdapter(),
    ),
    defaultStorage: new LocalFilesystemStorage(''),  // no basePath → use full absolute paths
    defaultLanguage: 'es',       // language used when none is specified
    fallbackLanguage: 'en',      // final fallback if requested language is not found
);
```

## Registering Prompt Directories

There are two ways to register prompts — `autoloadFrom()` is the recommended approach.

### Option A — `autoloadFrom()` (recommended)

Auto-discovers and registers every namespace found under `resources/prompts/`. Source name is read automatically from `composer.json`:

```php
// Scans {basePath}/resources/prompts/{namespace}/ and registers everything
$registry->autoloadFrom(__DIR__ . '/..');  // pass the library root

// Or override the source name
$registry->autoloadFrom(__DIR__ . '/..', 'my-custom-source');
```

This is the pattern used by `nexus-ai-prompts-articles` and other extension libraries — just call `autoloadFrom()` in your bootstrap and all prompts are registered automatically.

### Option B — `registerDirectory()` (manual)

For fine-grained control, or when the library doesn't follow the standard layout:

```php
// Register one namespace at a time
$registry->registerDirectory(
    path: __DIR__ . '/vendor/token27/nexus-ai-prompts-articles/resources/prompts/article',
    namespace: 'article',
    source: 'token27/nexus-ai-prompts-articles',  // use vendor/package format
);
```

> **Source name convention:** Use `vendor/package` format (same as Composer) for the source name. This ensures compatibility with `PromptFinder::scan()` which also uses this format.

## Directory Structure

```
{path}/                     ← this is what you pass to registerDirectory()
  research/                 ← prompt type
    v1.0.0/                 ← version
      es.json               ← Spanish prompt
      en.json               ← English prompt
    v2.0.0/
      es.json
  plan/
    v1.0.0/
      es.json
      en.json
```

The identifier for `research/v1.0.0/es.json` would be `article/research` (namespace + type).

## Per-Source Storage (Advanced)

If you want different storage backends per source (e.g., Flysystem for remote, local for dev), use `registerStorage()`:

```php
use Token27\NexusAI\Prompts\Storage\FlysystemStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$filesystem = new Filesystem(new LocalFilesystemAdapter('/base/path'));
$registry->registerStorage('my-source', new FlysystemStorage($filesystem));
```

> When no per-source storage is registered, the `defaultStorage` passed to the constructor is used for all sources.

## Framework Integration

### Laravel (simple binding)

```php
// AppServiceProvider
$this->app->singleton(PromptRegistry::class, function () {
    $registry = new PromptRegistry(/* ... */);
    $registry->registerDirectory(resource_path('prompts'), 'app', 'app');
    return $registry;
});
```

### Symfony (autowired)

```yaml
# config/services.yaml
Token27\NexusAI\Prompts\PromptRegistry:
    factory: ['App\Factory\PromptRegistryFactory', 'create']
```

### Standalone (no framework)

```php
// bootstrap.php
$registry = require __DIR__ . '/config/prompt_registry.php';
```
