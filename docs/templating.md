# Mustache Templating

All prompt content is rendered via [Mustache](https://mustache.github.io/). By default, the library includes a `MustacheAdapter` wrapping `mustache/mustache ^2.14`.

## Basic Substitution

```json
{
    "blocks": [
        { "role": "user", "content": "Write about: {{topic}}" }
    ],
    "variables": {
        "topic": { "type": "string", "required": true }
    }
}
```

```php
$rendered = $prompt->render(['topic' => 'Machine Learning']);
// content becomes: "Write about: Machine Learning"
```

## Conditional Sections (`{{#variable}}...{{/variable}}`)

The section renders only when `variable` is truthy (non-empty string, non-zero number, non-empty array, `true`):

```json
{
    "blocks": [
        {
            "role": "user",
            "content": "Topic: {{topic}}{{#context}}\n\nContext: {{context}}{{/context}}"
        }
    ],
    "variables": {
        "topic":   { "type": "string", "required": true },
        "context": { "type": "string", "required": false, "default": null }
    }
}
```

## Inverted Sections (`{{^variable}}...{{/variable}}`)

Renders when `variable` is **falsy** (empty, null, false, 0). Useful for providing defaults within the template:

```json
"content": "{{^persona}}You are a helpful assistant.{{/persona}}{{#persona}}You are {{persona}}.{{/persona}}"
```

## Iterating Arrays (`{{#array}}{{.}}{{/array}}`)

```json
{
    "blocks": [
        {
            "role": "user",
            "content": "Keywords: {{#keywords}}{{.}}, {{/keywords}}"
        }
    ],
    "variables": {
        "keywords": { "type": "array", "required": false, "default": [] }
    }
}
```

If keywords is `['SEO', 'AI']`, the content becomes: `"Keywords: SEO, AI, "`.

## Lambdas and Helpers

You can register helpers to perform transformations (like uppercase) directly in your templates.

```php
$engine = new MustacheAdapter();
$engine->registerHelper('uppercase', fn($text) => strtoupper($text));

// Usage in template: {{#uppercase}}hello{{/uppercase}} -> HELLO
```

## Variable Type Safety

The registry enforces variable declarations. If a variable is used in a block but not defined in the `variables` object, a `VariableValidationException` is thrown at render time.

| Type | PHP Equivalent |
|---|---|
| `"string"` | `string` |
| `"number"` | `int|float` |
| `"boolean"` | `bool` |
| `"array"` | `array` |

## Swapping Engines

`MustacheAdapter` implements `TemplateEngineInterface`. You can provide your own adapter for Twig, Blade, or any other engine.

```php
interface TemplateEngineInterface
{
    public function render(string $template, array $variables): string;
    public function supportsHelpers(): bool;
    public function registerHelper(string $name, callable $helper): void;
}
```
