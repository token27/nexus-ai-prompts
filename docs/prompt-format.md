# Prompt Format

Every prompt is a single JSON file. The filename encodes the language (`es.json`, `en.json`), and its path within the registered directory encodes the type and version.

## File Location Convention

```
{namespace_root}/{type}/v{version}/{lang}.json
```

Example: `article/research/v1.0.0/es.json` → identifier `article/research`, version `1.0.0`, language `es`.

## Full Schema (v1.0.0)

```json
{
    "meta": {
        "version": "1.0.0",
        "prompt_type": "research",
        "language": "es",
        "created_at": "2026-05-18T00:00:00Z",
        "cost_estimated": 0.02,
        "model_hints": ["gpt-4o", "claude-3-5-sonnet"],
        "category": "article",
        "tags": ["seo", "research", "web"]
    },
    "blocks": [
        {
            "role": "system",
            "content": "Eres un experto en SEO y {{category}}."
        },
        {
            "role": "user",
            "content": "Investiga el siguiente tema: {{topic}}"
        }
    ],
    "variables": {
        "topic": {
            "type": "string",
            "required": true
        },
        "category": {
            "type": "string",
            "required": false,
            "default": "contenido web"
        }
    }
}
```

## Field Reference

### `meta` (required)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `version` | string | ✅ | Semantic version of the prompt (`1.0.0`) |
| `prompt_type` | string | ✅ | Type identifier matching the directory name |
| `language` | string | ✅ | ISO language code (`es`, `en`, `es_AR`) |
| `created_at` | string | — | ISO 8601 creation date |
| `cost_estimated` | float | — | Estimated USD cost per call |
| `model_hints` | array | — | Suggested model IDs |
| `category` | string | — | Logical grouping for discovery |
| `tags` | array | — | Free-form tags for filtering |

### `blocks` (required)

An ordered array of objects. Each block represents a part of the prompt.

| Field | Values | Required | Description |
|-------|--------|----------|-------------|
| `content` | string\|array | ✅ | Mustache template string or vision array |
| `role` | string | - | Optional. `system`, `user`, `assistant`. |
| `weight` | float | - | Optional. Used by Stability AI (SDXL). |
| `name` | string | - | Optional. Block identifier or author name. |

**Multimodal Support:**
The `content` can be an array of parts for vision models:

```json
"blocks": [
    {
        "role": "user",
        "content": [
            { "type": "text", "text": "What is in this image?" },
            { "type": "image_url", "image_url": { "url": "{{image_uri}}" } }
        ]
    }
]
```

### `variables` (required)

Declares every variable used in the blocks.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | ✅ | `"string"`, `"number"`, `"boolean"`, `"array"` |
| `required` | boolean | ✅ | If `true`, must be provided at render time |
| `default` | mixed | — | Used when variable is absent and `required: false` |

## Variable Validation

Required variables that are missing at render time throw `VariableValidationException`. Non-declared variables also throw an exception to ensure prompt integrity.

## Naming Conventions

- `prompt_type`: kebab-case (`web-search`)
- `version`: semantic (`1.0.0`)
- `language`: ISO (`es`, `en`, `es_AR`)
- `variables`: snake_case (`max_tokens`)
