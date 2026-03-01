# Multi-Provider AI System

---

- [Overview](#overview)
- [Built-in Providers](#built-in-providers)
- [Configuring the Default Provider](#configuring-the-default-provider)
- [Text vs Image Providers](#text-vs-image-providers)
- [Switching Providers at Runtime](#switching-providers)
- [AiProviderManager](#ai-provider-manager)
- [Custom Providers](#custom-providers)

<a name="overview"></a>
## Overview

Scribe AI supports multiple AI backends through the `AiProvider` contract. All providers normalize their responses to a common OpenAI-compatible format, so stages and services work identically regardless of the provider in use.

<a name="built-in-providers"></a>
## Built-in Providers

| Provider | Text/Chat | Images | Env Key |
|----------|-----------|--------|---------|
| [OpenAI](/docs/1.0/provider-openai) | ✅ GPT-4o, GPT-4o-mini | ✅ DALL-E 3, gpt-image-1 | `OPENAI_API_KEY` |
| [Claude](/docs/1.0/provider-claude) | ✅ Claude 3.5 Sonnet, Claude 3 Opus | ❌ | `ANTHROPIC_API_KEY` |
| [Gemini](/docs/1.0/provider-gemini) | ✅ Gemini 2.0 Flash, 1.5 Pro | ✅ Imagen | `GEMINI_API_KEY` |
| [Ollama](/docs/1.0/provider-ollama) | ✅ Llama 3, Mistral (local) | ❌ | None (local) |
| [PiAPI](/docs/1.0/provider-piapi) | ❌ | ✅ Flux | `PIAPI_API_KEY` |

<a name="configuring-the-default-provider"></a>
## Configuring the Default Provider

Set the default text/chat provider in `.env`:

```dotenv
AI_PROVIDER=openai
```

All AI operations (content rewriting, SEO suggestions, category selection) will use this provider.

<a name="text-vs-image-providers"></a>
## Text vs Image Providers

Text and image generation can use **different** providers. This allows combinations like "Claude for writing, OpenAI for images" or "Gemini for writing, PiAPI Flux for images":

```dotenv
# Text provider
AI_PROVIDER=claude

# Image provider (separate)
AI_IMAGE_PROVIDER=openai
```

**Fallback logic for image provider:**

1. If `AI_IMAGE_PROVIDER` is set → use that provider
2. If the default text provider supports images → use it
3. Last resort → fall back to OpenAI

<a name="switching-providers"></a>
## Switching Providers at Runtime

```php
use Badr\ScribeAi\Services\Ai\AiProviderManager;

$manager = app(AiProviderManager::class);

// Use the default provider
$response = $manager->provider()->chat($messages, 'gpt-4o-mini', 2000);

// Use a specific provider
$response = $manager->provider('claude')->chat($messages, 'claude-sonnet-4-20250514', 2000);

// Get the image provider
$imageProvider = $manager->imageProvider();
$imageData = $imageProvider->generateImage('A cat on a laptop', 'dall-e-3', '1024x1024', 'standard');
```

<a name="ai-provider-manager"></a>
## AiProviderManager

The `AiProviderManager` is the central registry for all providers.

**Key methods:**

| Method | Description |
|--------|-------------|
| `provider(?string $name)` | Resolve a provider by name (null = default) |
| `imageProvider()` | Resolve the image generation provider |
| `extend(string $name, Closure $creator)` | Register a custom provider |
| `getDefaultProvider()` | Get the default provider name from config |
| `available()` | List all provider names (built-in + custom) |

Providers are cached after first resolution for the lifetime of the request.

<a name="custom-providers"></a>
## Custom Providers

Register a custom provider in your service provider's `register()` method:

```php
use Badr\ScribeAi\Services\Ai\AiProviderManager;

app(AiProviderManager::class)->extend('mistral', function (array $config) {
    return new MistralProvider($config);
});
```

Your class must implement `Badr\ScribeAi\Contracts\AiProvider`. See [Custom Providers](/docs/1.0/provider-custom) for a full guide.
