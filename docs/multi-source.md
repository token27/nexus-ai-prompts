# Multi-Source & Collision Handling

One of the core design goals of `nexus-ai-prompts` is to be the **shared prompt registry for the entire nexus-ai ecosystem**. Multiple libraries — each owning their own prompts — can all register into the same registry instance without collisions, as long as they use unique source names.

## The Problem

Without source awareness, if `lib-articles` and `lib-social` both register a prompt under `content/template`, the second registration silently overwrites the first. You can never tell which prompt you're using.

`nexus-ai-prompts` solves this by treating prompts as `source:identifier:version:language` tuples, not just `identifier:version:language`.

## Registering Multiple Sources

```php
// Each library registers itself with a unique source name
$registry->registerDirectory('/path/articles/prompts/article', 'article', 'lib-articles');
$registry->registerDirectory('/path/social/prompts/social',    'social',  'lib-social');
$registry->registerDirectory('/path/nexus/prompts/example',    'example', 'nexus-ai-prompts');
```

## Explicit Source Resolution (Recommended)

Always specify the source when you know which library owns the prompt:

```php
$prompt = $registry->resolve('article/research', '1.0.0', 'es', 'lib-articles');
// Fast: skips scanning other sources, no ambiguity check
```

## Implicit Source Resolution (Auto-Discover)

Omit the source to let the registry search all registered sources:

```php
$prompt = $registry->resolve('article/research', '1.0.0', 'es');
// Searches lib-articles, lib-social, nexus-ai-prompts
```

**If the identifier is found in exactly one source:** resolves cleanly.

**If the identifier is found in multiple sources:** throws `AmbiguousPromptException`.

## AmbiguousPromptException

```php
use Token27\NexusAI\Prompts\Exception\AmbiguousPromptException;

try {
    $prompt = $registry->resolve('content/template', '1.0.0', 'es');
} catch (AmbiguousPromptException $e) {
    echo $e->getMessage();
    // Prompt "content/template" is ambiguous. Found in sources: [lib-articles, lib-social].
    // Specify a source explicitly to resolve this ambiguity.

    $e->getIdentifier(); // 'content/template'
    $e->getSources();    // ['lib-articles', 'lib-social']
}
```

## Duplicate Detection with PromptFinder

Use `PromptFinder::getDuplicates()` at startup to detect potential ambiguities before they cause runtime errors:

```php
use Token27\NexusAI\Prompts\Discovery\PromptFinder;

$finder = new PromptFinder($registry);
$duplicates = $finder->getDuplicates();

if (!empty($duplicates)) {
    foreach ($duplicates as $identifier => $sources) {
        logger()->warning("Ambiguous prompt '{$identifier}' found in: " . implode(', ', $sources));
    }
}
```

This is especially useful in service container boot:

```php
// AppServiceProvider or kernel event
$finder = new PromptFinder($registry);
foreach ($finder->getDuplicates() as $id => $sources) {
    // Log or alert — but don't fail, as explicit access is always safe
}
```

## Source-Specific Storage

Each source can use a different `StorageInterface` for fetching files:

```php
// Default storage (local, fast) for your own prompts
$registry = new PromptRegistry(
    loader: ...,
    defaultStorage: new LocalFilesystemStorage(''),
    ...
);

// Per-source: remote source via Flysystem
$registry->registerStorage('remote-lib', new FlysystemStorage($s3Filesystem));
```

## Naming Convention for Sources

| Package | Recommended source name |
|---------|------------------------|
| `token27/nexus-ai-prompts` | `nexus-ai-prompts` |
| `token27/nexus-ai-prompts-articles` | `nexus-ai-prompts-articles` |
| `myvendor/my-prompts` | `myvendor-my-prompts` |
| Application-level prompts | `app` |

> **Rule:** Use the full Composer package name with slashes replaced by hyphens. This guarantees global uniqueness.

## Integration Pattern for Library Authors

If you are creating a library that ships prompts, export a registration helper:

```php
// In your library's service provider or bootstrap
namespace MyVendor\MyLib;

use Token27\NexusAI\Prompts\Contract\PromptRegistryInterface;

final class PromptRegistration
{
    public static function register(PromptRegistryInterface $registry): void
    {
        $registry->registerDirectory(
            path: __DIR__ . '/../resources/prompts/my-namespace',
            namespace: 'my-namespace',
            source: 'myvendor-my-lib',
        );
    }
}
```

Then in the consuming application:

```php
PromptRegistration::register($registry);
```
