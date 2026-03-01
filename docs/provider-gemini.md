# Gemini Provider

---

- [Overview](#overview)
- [Configuration](#configuration)
- [Supported Models](#supported-models)
- [How It Works](#how-it-works)
- [Image Generation](#image-generation)
- [JSON Mode](#json-mode)

<a name="overview"></a>
## Overview

The Gemini provider integrates Google's Gemini family of models via the `generateContent` endpoint. It supports both text/chat completions and image generation (via Imagen), making it a versatile all-in-one option.

<a name="configuration"></a>
## Configuration

```dotenv
AI_PROVIDER=gemini
GEMINI_API_KEY=AIza...
```

Provider-specific config in `config/scribe-ai.php`:

```php
'providers' => [
    'gemini' => [
        'api_key'  => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
],
```

<a name="supported-models"></a>
## Supported Models

### Chat/Text
- `gemini-2.0-flash` — fast, high quality (recommended)
- `gemini-1.5-pro` — larger context window
- `gemini-1.5-flash` — fast and efficient

### Image Generation
- `imagen-3.0-generate-002` — Google's Imagen model
- Any model supporting `responseModalities: ['TEXT', 'IMAGE']`

<a name="how-it-works"></a>
## How It Works

The Gemini provider translates between Scribe AI's internal format and the Gemini API:

1. **System messages** → extracted into the `systemInstruction` parameter
2. **User messages** → `role: user` with `parts: [{ text: "..." }]`
3. **Assistant messages** → `role: model` (Gemini's term for assistant)
4. **Response** → candidates are parsed and normalized to the OpenAI schema

**API endpoint pattern:**

```
{base_url}/models/{model}:generateContent?key={api_key}
```

<a name="image-generation"></a>
## Image Generation

Gemini uses the same `generateContent` endpoint with `responseModalities: ['TEXT', 'IMAGE']`. The provider extracts inline base64 image data from the response.

```dotenv
AI_PROVIDER=gemini
AI_IMAGE_PROVIDER=gemini
OPENAI_IMAGE_MODEL=imagen-3.0-generate-002
```

You can also use Gemini for text and another provider for images:

```dotenv
AI_PROVIDER=gemini
AI_IMAGE_PROVIDER=piapi
```

<a name="json-mode"></a>
## JSON Mode

Gemini has native JSON mode support. When `jsonMode: true` is requested:

1. `responseMimeType: 'application/json'` is added to `generationConfig`
2. A system instruction is also appended as a safety net

This produces reliable JSON output directly from the API.
