# Troubleshooting guide

This guide covers the most common exceptions and errors you might encounter when using `nexus-ai-prompts`, along with their solutions.

## Exceptions

The library uses a specific set of exceptions within the `Token27\NexusAI\Prompts\Exception\` namespace to precisely communicate errors.

### 1. `PromptNotFoundException`

**Thrown when:**
`PromptRegistry::resolve()` cannot find a prompt matching the given `identifier`, `version`, and `language` cascade.

**Common causes & solutions:**

- **Language not available:** You requested `fr`, but neither `fr.json` nor `en.json` (fallback) exist. Make sure your language cascade completes properly.
- **Wrong version logic:** A request for `latest` (default) but the directory `v{version}` has no JSON files. Ensure the directories start with a `v` (e.g. `v1.0.0`).
- **Autoload failure:** `autoloadFrom()` was called but the `resources/prompts/` directory was empty or missing. Check directory paths.

### 2. `AmbiguousPromptException`

**Thrown when:**
The same prompt `identifier` exists in more than one registered source, and you didn't specify which source to use.

**Example:**
Both `token27/nexus-ai-prompts-articles` and `vendedorX/nexus-ai-prompts-custom` have a prompt named `article/research`.

**Solution:**
Provide the `$source` explicitly when resolving:

```php
$prompt = $registry->resolve('article/research', source: 'token27/nexus-ai-prompts-articles');
```

*Tip: You can use `PromptFinder::scan()` or `PromptFinder->getDuplicates()` to audit your system for overlapping identifiers.*

### 3. `VariableValidationException`

**Thrown when:**
A prompt has variables marked as `"required": true` in the JSON file, but you did not provide them in the `render()` parameters.

**Solution:**
Inspect the prompt's `variables` schema and ensure all required keys are passed:

```php
// JSON requires "topic"
$prompt->render(['topic' => 'AI']);
```

### 4. `InvalidPromptSchemaException`

**Thrown when:**
The JSON file does not conform to the expected format (missing `meta`, `blocks`, or `variables` root keys), or is invalid JSON.

**Solution:**
Check the structure of your JSON. It must contain the 3 base objects. See [Prompt Format](prompt-format.md) for details. Use a JSON validator to catch syntax errors.

### 5. `StorageException`

**Thrown when:**
The underlying storage system (Local disk or Flysystem) cannot read a directory or file.

**Solution:**
Check file permissions (`chmod`/`chown`). If using a cloud bucket via Flysystem, verify your credentials and bucket access policies.

---

## Common Runtime Errors

### `RuntimeException` on `render()`

**Error:** `Cannot render: no blocks have been added.`
**Thrown when:** You call `render()` on a `PromptBuilder` before adding any `system()`, `user()`, or `text()` blocks.

**Solution:**
Always initialize your builder with at least one block:

```php
PromptEngine::build()
    ->text('Hello World') // Add this!
    ->render();
```

### Prompt string contains unresolved `{{var}}`

**Cause:** You passed a variable to `render()`, but the key name does not perfectly match the variable name in the template block. Mustache fails silently for non-strict missing variables, leaving them blank, but if the variable wasn't declared in the JSON as required, you won't get a `VariableValidationException`.

**Solution:**
Always declare your variables in the `variables` key of your JSON file. This guarantees validation *before* rendering.
