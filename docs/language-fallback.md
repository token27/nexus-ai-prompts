# Language Fallback

`nexus-ai-prompts` automatically handles language fallback so that your prompts can serve the best available language rather than failing outright when a specific locale isn't available.

## How It Works

When you request a prompt with language `es_AR`, the registry builds a **cascade** of candidates and tries each in order:

```
es_AR → es → {fallbackLanguage}
```

The first file found on disk is loaded. If none are found, `PromptNotFoundException` is thrown.

## Cascade Rules

| Requested language | Cascade tried |
|--------------------|---------------|
| `en` | `en → {fallback}` |
| `es` | `es → {fallback}` |
| `es_AR` | `es_AR → es → {fallback}` |
| `zh_TW` | `zh_TW → zh → {fallback}` |
| `pt_BR` | `pt_BR → pt → {fallback}` |

For any regional locale (`xx_YY`), the base language (`xx`) is always inserted before the fallback.

## Configuration

The fallback language is set when constructing the registry:

```php
$registry = new PromptRegistry(
    loader: ...,
    defaultStorage: ...,
    defaultLanguage: 'es',   // used when resolve() is called without a language
    fallbackLanguage: 'en',  // final fallback in the cascade
);
```

## Examples

### Basic fallback

```
Files on disk: research/v1.0.0/en.json
Request: resolve('article/research', '1.0.0', 'fr')

Cascade: fr → en
→ 'fr.json' not found
→ 'en.json' found ✓

$prompt->getLanguage(); // 'en'
```

### Regional locale fallback

```
Files on disk: research/v1.0.0/es.json  research/v1.0.0/en.json
Request: resolve('article/research', '1.0.0', 'es_AR')

Cascade: es_AR → es → en
→ 'es_AR.json' not found
→ 'es.json' found ✓

$prompt->getLanguage(); // 'es'
```

### No file at all

```
Files on disk: research/v1.0.0/en.json
Request: resolve('article/research', '1.0.0', 'es_AR', 'my-src')

Cascade: es_AR → es → en
→ 'es_AR.json' not found
→ 'es.json' not found
→ 'en.json' found ✓

$prompt->getLanguage(); // 'en'
```

### Nothing found

```
Files on disk: (empty)
Request: resolve('article/research', '1.0.0', 'es', 'my-src')

Cascade: es → en
→ 'es.json' not found
→ 'en.json' not found
→ throws PromptNotFoundException
```

## Checking What Language Was Resolved

After `resolve()`, always check the actual language via `getLanguage()` if you need to know:

```php
$prompt = $registry->resolve('article/research', '1.0.0', 'es_AR', 'my-lib');

if ($prompt->getLanguage() !== 'es_AR') {
    // A fallback was used — log or localise output accordingly
    logger()->info("Prompt language fallback: using '{$prompt->getLanguage()}'");
}
```

## Default Language

The `defaultLanguage` is the language used when `resolve()` is called without an explicit language:

```php
// registry built with defaultLanguage: 'es'
$prompt = $registry->resolve('article/research', '1.0.0');
// tries 'es' first, then falls back per cascade
```

## Supported File Names

Language files must be named exactly as the language code + `.json`:

| Valid | Invalid |
|-------|---------|
| `es.json` | `spanish.json` |
| `en.json` | `EN.json` |
| `es_AR.json` | `es-AR.json` |
| `zh_TW.json` | `zh-tw.json` |
