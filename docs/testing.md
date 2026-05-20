# Testing

## Running the Test Suite

```bash
# Run all tests (130+)
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse
```

## Internal Tests

The library is strictly tested across multiple layers:

- **`PromptTest`**: Verifies variable interpolation and block rendering.
- **`RenderedPromptTest`**: Verifies 8 output formatters (OpenAI, Anthropic, Gemini, etc.).
- **`PromptBuilderTest`**: Verifies the fluent API.
- **`PromptRegistryIntegrationTest`**: End-to-end resolution with real JSON fixtures.

## Testing Your Prompts

The recommended way to test your custom prompts is to resolve them through a registry and verify the rendered output.

```php
public function testMyPrompt(): void
{
    $rendered = $registry->resolve('my/prompt')->render(['var' => 'val']);

    // Check the final payload for your specific provider
    $payload = $rendered->asOpenAI();
    
    $this->assertCount(1, $payload);
    $this->assertSame('val', $payload[0]['content']);
}
```

## JSON Fixtures

When creating test fixtures, ensure they use the `blocks` key:

```json
{
    "meta": { "version": "1.0.0", "prompt_type": "test", "language": "en" },
    "blocks": [
        { "role": "user", "content": "Template: {{var}}" }
    ],
    "variables": {
        "var": { "type": "string", "required": true }
    }
}
```

## PHPUnit Configuration

The `phpunit.xml.dist` is configured to:

- Suppress vendor deprecations (primarily from the Mustache engine on PHP 8.4).
- Fail on risky tests or warnings.
- Restrict logic tracking to the `src/` directory.
