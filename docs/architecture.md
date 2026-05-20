# Architecture

## Layers

```
┌─────────────────────────────────────────────────────────────┐
│                     Consumer Code                           │
│   $registry->resolve() · PromptEngine · PromptBuilder       │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                   PromptRegistry                            │
│  Central hub for versioned prompts across multiple sources  │
└──────────┬──────────────────────────────────┬───────────────┘
           │                                  │
┌──────────▼──────────┐          ┌────────────▼──────────────┐
│    PromptLoader     │          │      PromptFinder          │
│  Validate JSON      │          │  List identifiers, versions│
│  Parse JSON → Prompt│          │  Detect duplicates, catalog│
└──────────┬──────────┘          └────────────────────────────┘
           │
      ┌─────┴──────┐          ┌───────────────────────┐
      ▼            ▼          │   RenderedPrompt      │
  Storage      Mustache       │   8 named as*()       │
  Backend      Adapter        │   Terminal methods    │
      │                       └───────────┬───────────┘
┌─────┴──────────┐                        │
│ Local / Fly    │              ┌─────────▼─────────┐
└────────────────┘              │ Output Formatters │
                                │ OpenAI, Anthropic,│
                                │ Gemini, SD, etc.  │
                                └───────────────────┘
```

## Internal Workflow

### 1. Discovery & Load

When calling `$registry->resolve('article/research')`:

1. The **Registry** builds a language cascade (e.g. `es_AR` → `es` → `en`).
2. It asks the **Storage** if the JSON file exists for each candidate.
3. The **Loader** reads the JSON and delegates validation to the **SchemaValidator**.
4. A **Prompt** object is instantiated and cached.

### 2. Render & Format

When calling `$prompt->render(['topic' => 'AI'])`:

1. The **Prompt** validates the input variables against the declarations.
2. The **MustacheAdapter** renders each block's content.
3. A **RenderedPrompt** is returned. This is the terminal object.
4. Calling `asOpenAI()`, `asAnthropic()`, etc., triggers an **OutputFormatter** to structure the final payload.

## Core Concepts

### Block-based Architecture

Unlike v1 which was hardcoded to Chat messages, v2 treats prompts as a collection of flexible **Blocks**. A block can have a `role`, but it can also have a `weight` (for image models), a `name`, or any other extension key. This makes the library truly universal.

### Decoupled Formatting

The library never assumes a target API format during rendering. Formatting is an explicit terminal step (`->asAnthropic()`). This prevents vendor lock-in and simplifies testing.

### Storage Independence

All file operations go through `StorageInterface`. You can swap the default `LocalFilesystemStorage` for a `FlysystemStorage` to pull prompts from S3, Azure, or remote Git repos with zero changes to your application code.
