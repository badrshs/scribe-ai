# OpenAI Provider

---

- [Overview](#overview)
- [Configuration](#configuration)
- [Supported Models](#supported-models)
- [Chat Completions](#chat-completions)
- [Image Generation](#image-generation)
- [Custom Base URL](#custom-base-url)

<a name="overview"></a>
## Overview

The OpenAI provider supports both text/chat completions (GPT-4o, GPT-4o-mini, o1, o3) and image generation (DALL-E 3, gpt-image-1). It is the default provider and the final fallback for image generation.

<a name="configuration"></a>
## Configuration

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_CONTENT_MODEL=gpt-4o-mini
OPENAI_FALLBACK_MODEL=gpt-4o-mini
OPENAI_IMAGE_MODEL=dall-e-3
OPENAI_IMAGE_SIZE=1024x1024
OPENAI_IMAGE_QUALITY=standard
OPENAI_MAX_TOKENS=4096
```

Provider-specific config in `config/scribe-ai.php`:

```php
'providers' => [
    'openai' => [
        'api_key'  => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
],
```

> {info} For backward compatibility, the top-level `OPENAI_API_KEY` is automatically merged into the OpenAI provider config even if not set under `providers.openai`.

<a name="supported-models"></a>
## Supported Models

### Chat/Text
- `gpt-4o` — flagship model, excellent quality
- `gpt-4o-mini` — fast and cost-effective (recommended default)
- `o1`, `o3` — reasoning models (use `max_completion_tokens` parameter automatically)
- `gpt-4-turbo` — previous generation

### Image Generation
- `dall-e-3` — high-quality images, 1024×1024 or 1792×1024
- `dall-e-2` — faster, lower quality
- `gpt-image-1` — newer native GPT image generation

<a name="chat-completions"></a>
## Chat Completions

The provider sends requests to `/chat/completions`. It automatically detects newer models (gpt-5, o1, o3) and uses `max_completion_tokens` instead of the deprecated `max_tokens` parameter.

When JSON mode is enabled, `response_format: { type: "json_object" }` is sent.

```php
$provider = app(AiProviderManager::class)->provider('openai');

$response = $provider->chat(
    messages: [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Summarize this article...'],
    ],
    model: 'gpt-4o-mini',
    maxTokens: 2000,
    jsonMode: false,
);

$text = $response['choices'][0]['message']['content'];
```

<a name="image-generation"></a>
## Image Generation

The provider sends requests to `/images/generations`. For DALL-E 2/3, it requests `b64_json` to avoid URL expiration. For newer models (gpt-image-1), it downloads from the returned URL.

```php
$provider = app(AiProviderManager::class)->provider('openai');

$imageBytes = $provider->generateImage(
    prompt: 'A serene mountain landscape at sunset',
    model: 'dall-e-3',
    size: '1024x1024',
    quality: 'standard',
);

// $imageBytes is raw binary data ready for Storage::put()
```

<a name="custom-base-url"></a>
## Custom Base URL

Point to a compatible proxy or alternative endpoint:

```dotenv
OPENAI_BASE_URL=https://my-proxy.example.com/v1
```

This is useful for Azure OpenAI, OpenRouter, or any OpenAI-compatible API.
