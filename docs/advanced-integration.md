# Integración Avanzada (Advanced Integration)

This guide covers advanced usage scenarios when incorporating `nexus-ai-prompts` into a larger enterprise application or an abstract ecosystem.

## 1. Zero-Config Ecosystem Discovery

If you are developing a modular application where multiple composer packages (or internal libraries) supply their own prompts, you can use `PromptFinder::scan()` to discover them without manually invoking `autoloadFrom` everywhere.

```php
use Token27\NexusAI\Prompts\Discovery\PromptFinder;
use Token27\NexusAI\Prompts\PromptRegistry;
use Token27\NexusAI\Prompts\Loader\PromptLoader;
use Token27\NexusAI\Prompts\Storage\LocalFilesystemStorage;

$registry = new PromptRegistry(
    new PromptLoader(),
    new LocalFilesystemStorage()
);

// 1. Discover all prompt libraries in your vendor tree
$scanResults = PromptFinder::scan();

// 2. Automatically register them into the Registry
foreach ($scanResults as $library) {
    // We register the base directory containing the `resources/prompts`
    $baseLibDir = dirname($library['path']);
    $registry->autoloadFrom($baseLibDir, $library['source']);
}

// Now you can resolve dynamically 
$prompt = $registry->resolve('article/research');
```

## 2. Dealing with Multi-Source Collisions

In ecosystems where third-party developers can create "Prompt Packs", name collisions are inevitable. If a third-party pack overrides your `article/research` prompt, you can configure your Dependency Injection container to enforce the strict source string based on the active feature context.

```php
class ArticleGenerator 
{
    public function __construct(
        private PromptRegistry $registry,
        private string $activePromptProvider // e.g., 'token27/nexus-ai-prompts-articles'
    ) {}

    public function generate(string $topic): array
    {
        // Safe from AmbiguousPromptException
        return $this->registry->resolve('article/research', source: $this->activePromptProvider)
             ->render(['topic' => $topic])
             ->asAnthropic();
    }
}
```

## 3. The `autoloadFrom` Source Logic

When you call `$registry->autoloadFrom(__DIR__)`, how does the registry decide what `source` name to assign?

1. **Composer JSON parsing:** It first attempts to read `<__DIR__>/composer.json`. If found, it parses the `"name"` property (`vendor/package`).
2. **Directory Fallback:** If there is no `composer.json` (such as in an internal flat-directory layout), it takes the last two segments of the directory path as the source name (e.g., `/project/libs/my-custom-prompts` becomes `libs/my-custom-prompts`).

For full safety in modular environments, it is recommended to pass a hardcoded source name:

```php
$registry->autoloadFrom(__DIR__ . '/..', 'internal/marketing-prompts');
```

## 4. On-the-Fly Formatting with Interfaces

If you integrate a new, unsupported AI provider (e.g., local LLaMA instance via a proprietary API wrapper), you don't need to fork `nexus-ai-prompts`.

Create your own formatter that implements `Token27\NexusAI\Prompts\Contract\OutputFormatterInterface`:

```php
use Token27\NexusAI\Prompts\Contract\OutputFormatterInterface;
use Token27\NexusAI\Prompts\ValueObject\RenderedPrompt;

class LlamaCustomFormatter implements OutputFormatterInterface 
{
    public function format(RenderedPrompt $prompt): array
    {
        $payload = ['instruction_set' => []];
        foreach ($prompt->getBlocks() as $block) {
            $payload['instruction_set'][] = $block['content'];
        }
        return $payload;
    }
}

// Usage:
$rendered = $registry->resolve('marketing/seo')->render($vars);
$formattedPayload = $rendered->format(new LlamaCustomFormatter());
```
