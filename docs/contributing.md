# Contributing

Thank you for considering a contribution to `nexus-ai-prompts`. This document covers development setup, coding standards, and how to add new backends, engines, and prompts.

## Development Setup

```bash
git clone <repo-url> nexus-ai-prompts
cd nexus-ai-prompts
composer install
```

## Running Checks

```bash
# All tests
vendor/bin/phpunit

# Static analysis (must pass at level 8)
vendor/bin/phpstan analyse --no-progress

# Code style (PSR-12)
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/php-cs-fixer fix   # auto-fix
```

All three commands must pass with zero errors/warnings before submitting a PR.

## Project Structure

```
src/
  Contract/       # Interfaces — do not modify without a discussion
  ValueObject/    # Immutable DTOs
  Exception/      # Custom exception classes
  Storage/        # LocalFilesystemStorage + FlysystemStorage
  Engine/         # MustacheAdapter
  Loader/         # PromptLoader + PromptSchemaValidator
  Discovery/      # PromptFinder
  Prompt.php
  PromptRegistry.php
  PromptEngine.php
  PromptBuilder.php
resources/
  prompts/
    example/      # Built-in example prompts (must remain generic, no domain-specific content)
tests/
  Unit/
  Integration/
  fixtures/
docs/             # This directory
```

## Coding Standards

- **PHP 8.3+** — use named arguments, readonly properties, enums, match expressions
- **PHPStan level 8** — every array must have its value type declared (e.g., `array<string, mixed>`)
- **PSR-12** code style enforced by `php-cs-fixer`
- **Immutable value objects** — `Prompt`, `RenderedPrompt`, `PromptMetadata`, `VariableDef` must never mutate
- **`declare(strict_types=1)`** in every file
- **No `static` state** outside of pure functions — the registry is an instance, not a singleton

## Adding a Storage Backend

1. Create your class under `src/Storage/`:

```php
// src/Storage/DynamoDbStorage.php
declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Storage;

use Token27\NexusAI\Prompts\Contract\StorageInterface;
use Token27\NexusAI\Prompts\Exception\StorageException;

final class DynamoDbStorage implements StorageInterface
{
    public function __construct(private readonly \Aws\DynamoDb\DynamoDbClient $client) {}

    public function read(string $path): string
    {
        // ...
        throw StorageException::cannotRead($path);
    }

    public function exists(string $path): bool { /* ... */ }

    /** @return list<string> */
    public function listDirectories(string $path): array { /* ... */ }

    /** @return list<string> */
    public function listFiles(string $path): array { /* ... */ }
}
```

1. Add tests under `tests/Unit/DynamoDbStorageTest.php`
2. Document it in `docs/storage.md`

## Adding a Template Engine

1. Create your class under `src/Engine/`:

```php
// src/Engine/TwigEngine.php
declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Engine;

use Token27\NexusAI\Prompts\Contract\TemplateEngineInterface;

final class TwigEngine implements TemplateEngineInterface
{
    public function __construct(private readonly \Twig\Environment $twig) {}

    /** @param array<string, mixed> $variables */
    public function render(string $template, array $variables): string
    {
        return $this->twig->createTemplate($template)->render($variables);
    }

    public function supportsHelpers(): bool { return false; }

    public function registerHelper(string $name, callable $helper): void
    {
        throw new \RuntimeException('Helpers not supported.');
    }
}
```

1. Add tests under `tests/Unit/TwigEngineTest.php`
2. Update `docs/templating.md`

## Adding Example Prompts

Built-in prompts live in `resources/prompts/example/`. They must be **generic** — no domain-specific content (no articles, no social media):

```
resources/prompts/example/
  my-new-type/
    v1.0.0/
      es.json
      en.json
```

Run `vendor/bin/phpunit` after adding — the integration test `testRealResourcePromptsCanBeLoaded` will catch schema issues.

## Adding a New Exception

All exceptions extend `\RuntimeException`. Create them under `src/Exception/`:

```php
declare(strict_types=1);

namespace Token27\NexusAI\Prompts\Exception;

final class MyNewException extends \RuntimeException
{
    public static function forSomething(string $context): self
    {
        return new self(sprintf('Something went wrong with "%s".', $context));
    }
}
```

## Versioning Guidelines

This library follows [Semantic Versioning](https://semver.org/):

| Change type | Version bump |
|-------------|-------------|
| New method on existing interface | **MAJOR** (breaking for implementors) |
| New class, new interface | **MINOR** |
| Bug fix, new optional constructor param | **PATCH** |
| New `StorageInterface` implementation | **MINOR** |
| New exception class | **MINOR** |

## Pull Request Checklist

- [ ] `vendor/bin/phpunit` passes (zero errors)
- [ ] `vendor/bin/phpstan analyse` passes (level 8, zero errors)
- [ ] `vendor/bin/php-cs-fixer fix --dry-run` shows no violations
- [ ] New functionality has unit **and** integration tests
- [ ] New public API is documented in the relevant `docs/*.md` file
- [ ] `CHANGELOG.md` entry added (if applicable)
- [ ] No internal dependencies added (zero `nexus-ai-*` requires)
