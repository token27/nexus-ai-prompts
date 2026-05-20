# Output Formatters

One of the core strengths of `nexus-ai-prompts` v1.0.0 is the decoupling of prompt rendering and API formatting. After rendering a prompt, you can format it for any supported AI provider using the `as*()` methods.

## The `RenderedPrompt` Object

Calling `render()` returns a `RenderedPrompt` object. This object holds the rendered text but doesn't assume any specific API structure.

```php
$rendered = $registry->resolve('article/writer')->render($vars);
```

---

## Supported Formatters

### 1. OpenAI (`asOpenAI`)

Formats the content as a flat array of messages with `role` and `content`.

**Format:** `list<array{role: string, content: string|array}>`

### 2. Anthropic (`asAnthropic`)

Anthropic requires a separate `system` string and a `messages` array starting with a `user` role. The formatter automatically extracts the `system` block and cleans the message list.

**Format:** `array{system?: string, messages: list<array{role: string, content: string|array}>}`

### 3. Gemini (`asGemini`)

Formats content for Google's Gemini SDK, using `contents` and `parts`.

**Format:** `array{contents: list<array{role: string, parts: list<array{text: string}>}>}`

### 4. Stability AI (`asStabilityAI`)

Ideal for SDXL and other image models. It converts blocks into `text_prompts` with optional `weight` support.

**Format:** `array{text_prompts: list<array{text: string, weight?: float}>}`

### 5. Ollama (`asOllama`)

Formats for the Ollama Chat API.

**Format:** `array{messages: list<array{role: string, content: string}>}`

### 6. Completion (`asCompletion`)

For legacy completion models that expect a single prompt string. Usually concatenates all blocks.

**Format:** `array{prompt: string}`

### 7. Embedding (`asEmbedding`)

For embedding models that expect a single input string.

**Format:** `array{input: string}`

### 8. Plain String (`asPlainString`)

Returns the prompt as a raw string. Useful for debugging or custom logging.

**Format:** `string`

---

## Custom Formats

If you need a format not covered by the built-in methods, you can use the generic `format()` method:

```php
// Pass a class that implements OutputFormatterInterface
$customPayload = $rendered->format(new MyCustomFormatter());

// Or access blocks directly for manual formatting
$blocks = $rendered->getBlocks();
```

## Multimodal Output

If your prompt blocks contain arrays (e.g., for vision), the formatters will preserve the structure as required by the specific provider (OpenAI and Anthropic vision schemas are automatically handled).
