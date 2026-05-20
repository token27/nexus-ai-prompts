# Fluent API (Engine & Builder)

The Fluent API allows you to create and render prompts on-the-fly without needing a pre-configured registry or physical files. This is ideal for dynamic workflows, testing, or apps that build prompts programmatically.

## PromptEngine

The `PromptEngine` class provides static factories to quickly create prompts.

### 1. `raw()` (Universal Prompt)

Creates a prompt from a raw string. Ideal for non-chat models (Image, Video, Completion).

```php
use Token27\NexusAI\Prompts\PromptEngine;

$rendered = PromptEngine::raw("A {{animal}} in space", ['animal' => 'cat'])
    ->render();

echo $rendered->asPlainString(); // "A cat in space"
```

### 2. `chat()` (Chat Prompt)

Creates a structured chat prompt from an array.

```php
$rendered = PromptEngine::chat([
    ['role' => 'system', 'content' => 'You are a poet'],
    ['role' => 'user', 'content' => 'Write about {{topic}}'],
], ['topic' => 'autumn'])->render();

$payload = $rendered->asOpenAI();
```

### 3. `build()` (Fluent Builder)

Returns an instance of `PromptBuilder` for complex, multi-step construction.

---

## PromptBuilder

The `PromptBuilder` allows you to construct a prompt block-by-block.

### Basic Usage

```php
use Token27\NexusAI\Prompts\PromptEngine;

$rendered = PromptEngine::build()
    ->system('You are an expert in {{domain}}')
    ->user('Explain {{topic}}')
    ->variables([
        'domain' => 'AI',
        'topic' => 'LLMs'
    ])
    ->render();

$payload = $rendered->asAnthropic();
```

### Methods

| Method | Description |
|---|---|
| `system(string\|array $content)` | Adds a block with role `system`. |
| `user(string\|array $content)` | Adds a block with role `user`. |
| `assistant(string\|array $content)` | Adds a block with role `assistant`. |
| `block(array $block)` | Adds a raw block array (useful for custom roles or metadata). |
| `variables(array $vars)` | Sets the variables for rendering. Used in strict Mustache validation. |
| `render()` | Executes the templating engine and returns a `RenderedPrompt`. <br>⚠ *Throws `RuntimeException` if called with 0 blocks.* <br>⚠ *Throws `VariableValidationException` if missing variables.* |

### Multimodal Blocks

You can pass arrays to the role methods for vision-compatible models:

```php
$builder->user([
    ['type' => 'text', 'text' => 'Describe this image'],
    ['type' => 'image_url', 'image_url' => ['url' => '...']]
]);
```

### Custom Blocks

For models that require specific keys (like `weight` for Stability AI), use the `block()` method:

```php
$builder->block([
    'content' => 'A photo of a cat',
    'weight' => 0.8
]);
```
