# Claude Provider

---

- [Overview](#overview)
- [Configuration](#configuration)
- [Supported Models](#supported-models)
- [How It Works](#how-it-works)
- [JSON Mode](#json-mode)
- [Limitations](#limitations)

<a name="overview"></a>
## Overview

The Claude provider integrates Anthropic's Claude models via the Messages API. Claude excels at long-form content generation and nuanced rewriting, making it an excellent choice for the content pipeline.

<a name="configuration"></a>
## Configuration

```dotenv
AI_PROVIDER=claude
ANTHROPIC_API_KEY=sk-ant-...
```

Provider-specific config in `config/scribe-ai.php`:

```php
'providers' => [
    'claude' => [
        'api_key'     => env('ANTHROPIC_API_KEY'),
        'base_url'    => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
    ],
],
```

<a name="supported-models"></a>
## Supported Models

- `claude-sonnet-4-20250514` — balanced speed and quality (recommended)
- `claude-3-5-sonnet-20241022` — previous generation, still excellent
- `claude-3-opus-20240229` — highest quality, slower
- `claude-3-haiku-20240307` — fastest, most cost-effective

Set the model via the content model config:

```dotenv
OPENAI_CONTENT_MODEL=claude-sonnet-4-20250514
OPENAI_FALLBACK_MODEL=claude-3-haiku-20240307
```

> {info} The `OPENAI_CONTENT_MODEL` env var is shared across all providers — it specifies which model string to send, regardless of the provider.

<a name="how-it-works"></a>
## How It Works

The Claude provider automatically translates between the OpenAI message format (used internally by Scribe AI) and the Anthropic Messages API:

1. **System messages** → extracted into the `system` parameter
2. **User/assistant messages** → passed directly via `messages` array
3. **Response** → normalized to OpenAI format: `['choices' => [['message' => ['content' => '...']]]]`

This translation is transparent — stages and services don't need to know which provider is active.

<a name="json-mode"></a>
## JSON Mode

Claude doesn't have a native JSON mode. When `jsonMode: true` is requested, the provider appends this instruction to the system prompt:

```
IMPORTANT: You MUST respond with ONLY a valid JSON object. No text before or after.
```

The `AiService::completeJson()` method strips any markdown fences from the response before parsing.

<a name="limitations"></a>
## Limitations

- **No image generation** — Claude does not support image generation. If using Claude as the default provider, set a separate image provider:

```dotenv
AI_PROVIDER=claude
AI_IMAGE_PROVIDER=openai
```

- **Rate limits** — Anthropic has per-model rate limits. The pipeline uses model fallback if the primary model fails.
