# PromptFinder — Discovery

`PromptFinder` provides two independent modes of prompt discovery:

- **Static scan** (zero-config, like CakePHP's `TaskFinder`) — scans the filesystem for any package that follows the prompt convention, no registry required
- **Registry-based** — queries an already-configured `PromptRegistry`

## 1. Static Scan (zero-config)

### Without any parameters

```php
use Token27\NexusAI\Prompts\Discovery\PromptFinder;

// Auto-detects vendor/ from this library's own location (__DIR__)
// Scans all packages for: resources/prompts/{namespace}/{type}/v{version}/{lang}.json
$results = PromptFinder::scan();
```

No registry, no configuration required. Works from any code that has the library installed.

### With explicit path

```php
$results = PromptFinder::scan(basePath: '/path/to/vendor');
```

### With filters

All filter parameters are optional and can be combined:

```php
$results = PromptFinder::scan(
    basePath: null,                                          // auto-detect
    excludeSources: ['token27/nexus-ai-prompts-legacy'],    // skip these packages
    excludeNamespaces: ['internal'],                         // skip these namespaces
    excludeIdentifiers: ['example/debug'],                   // skip specific prompts
    onlySources: ['token27/nexus-ai-prompts-articles'],     // scan ONLY these (overrides excludeSources)
    onlyNamespaces: ['article'],                             // scan ONLY these namespaces
);
```

> **Note:** `onlySources` takes precedence over `excludeSources`. Same for `onlyNamespaces` vs `excludeNamespaces`.

### Return value

```php
// $results is a list of entries, one per package found:
[
    [
        'source'  => 'token27/nexus-ai-prompts-articles',   // vendor/package (from directory structure)
        'path'    => '/var/www/vendor/.../resources/prompts',
        'prompts' => [
            [
                'identifier'           => 'article/research',
                'versions'             => ['1.0.0', '2.0.0'],     // sorted ascending
                'languages_per_version'=> [
                    '1.0.0' => ['en', 'es'],
                    '2.0.0' => ['en'],
                ],
            ],
        ],
    ],
]
```

### Auto-detection logic

`PromptFinder::scan()` locates the vendor directory automatically:

```
This file is at: {vendor}/token27/nexus-ai-prompts/src/Discovery/PromptFinder.php
                          ↑ 4 levels up = vendor directory
```

Both Composer layouts are supported:

- `{vendor}/{vendor-name}/{package}/resources/prompts/` (standard Composer)
- `{basePath}/{package}/resources/prompts/` (flat layout, sibling libraries)

---

## 2. Registry-based (instance mode)

Queries a pre-configured `PromptRegistry`. Useful when you already have a registry with registered sources:

```php
use Token27\NexusAI\Prompts\Discovery\PromptFinder;

$finder = new PromptFinder($registry);
```

### `findAll()`

Returns all identifiers grouped by source:

```php
$all = $finder->findAll();
// [
//   'token27/nexus-ai-prompts'          => ['example/websearch', 'example/create-skill'],
//   'token27/nexus-ai-prompts-articles' => ['article/research', 'article/plan'],
// ]
```

### `findBySource()`

```php
$ids = $finder->findBySource('token27/nexus-ai-prompts-articles');
// ['article/outline', 'article/plan', 'article/research']
```

### `findByNamespace()`

```php
$found = $finder->findByNamespace('article');
// ['token27/nexus-ai-prompts-articles' => ['article/research', 'article/plan']]
```

### `findByType()`

```php
$found = $finder->findByType('research');
// ['token27/nexus-ai-prompts-articles' => ['article/research']]
```

### `getDuplicates()`

Detects identifiers registered in more than one source (potential `AmbiguousPromptException`):

```php
$duplicates = $finder->getDuplicates();
// ['content/template' => ['lib-a', 'lib-b']]

if (!empty($duplicates)) {
    foreach ($duplicates as $identifier => $sources) {
        echo "⚠ '{$identifier}' exists in: " . implode(', ', $sources) . "\n";
    }
}
```

### `catalog()`

Full inventory with versions and languages per identifier:

```php
$catalog = $finder->catalog();
// [
//   [
//     'identifier'            => 'article/research',
//     'source'                => 'token27/nexus-ai-prompts-articles',
//     'versions'              => ['1.0.0', '2.0.0'],
//     'languages_per_version' => ['1.0.0' => ['en', 'es'], '2.0.0' => ['en']],
//   ],
// ]
```

---

## Practical use cases

### Health check endpoint

```php
// GET /health/prompts
$results = PromptFinder::scan();   // zero-config — no registry needed

return [
    'packages_found' => count($results),
    'sources'        => array_column($results, 'source'),
];

// Or with registry for duplicate detection:
$finder = new PromptFinder($registry);
$duplicates = $finder->getDuplicates();
return ['status' => empty($duplicates) ? 'ok' : 'warning', 'duplicates' => $duplicates];
```

### CLI list command (Laravel)

```php
class PromptsListCommand extends Command
{
    protected $signature = 'prompts:list {--source=}';

    public function handle(): void
    {
        $results = PromptFinder::scan(); // zero-config

        foreach ($results as $entry) {
            $this->line("<info>{$entry['source']}</info>");
            foreach ($entry['prompts'] as $prompt) {
                $versions = implode(', ', $prompt['versions']);
                $this->line("  └ {$prompt['identifier']} [{$versions}]");
            }
        }
    }
}
```

### Documentation generator

```php
$finder = new PromptFinder($registry);
$catalog = $finder->catalog();

foreach ($catalog as $entry) {
    echo "### `{$entry['identifier']}`\n";
    echo "- Source: `{$entry['source']}`\n";
    echo "- Versions: " . implode(', ', $entry['versions']) . "\n\n";
}
```

---

## Notes

- `scan()` is purely filesystem-based — it **does not load or parse JSON files**, only directory names
- Sources in `scan()` follow the `vendor/package` format derived from the directory structure
- Registry-based methods require a `PromptRegistryInterface` — calling them without one throws `\RuntimeException`
- `scan()` skips directories that do not contain `resources/prompts/`
