# PiAPI Provider (Flux)

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Polling Behaviour](#polling)
- [Size Formats](#size-formats)
- [Limitations](#limitations)

<a name="overview"></a>
## Overview

The PiAPI provider generates images using the Flux model via [piapi.ai](https://piapi.ai). It is an **image-only** provider — it does not support text/chat completions. Use it as a dedicated image provider alongside any text provider.

<a name="configuration"></a>
## Configuration

```dotenv
AI_IMAGE_PROVIDER=piapi
PIAPI_API_KEY=your-piapi-key
```

Optional tuning:

```dotenv
PIAPI_BASE_URL=https://api.piapi.ai
PIAPI_POLL_MAX_ATTEMPTS=30
PIAPI_POLL_INTERVAL_MS=3000
```

Provider-specific config in `config/scribe-ai.php`:

```php
'providers' => [
    'piapi' => [
        'api_key'          => env('PIAPI_API_KEY'),
        'base_url'         => env('PIAPI_BASE_URL', 'https://api.piapi.ai'),
        'poll_max_attempts' => (int) env('PIAPI_POLL_MAX_ATTEMPTS', 30),
        'poll_interval_ms'  => (int) env('PIAPI_POLL_INTERVAL_MS', 3000),
    ],
],
```

<a name="how-it-works"></a>
## How It Works

PiAPI uses an asynchronous task-based workflow:

1. **Create task** → `POST /api/flux/v1/run` with prompt, model, width, height
2. **Receive task_id** → PiAPI queues the generation
3. **Poll for result** → `GET /api/flux/v1/task/{task_id}` until status is `completed`
4. **Download image** → Fetch the raw image from the returned URL

```
Your App ──POST──→ PiAPI (task created)
         ←task_id─

Your App ──GET───→ PiAPI (status: processing)
         ←wait───

Your App ──GET───→ PiAPI (status: completed, image_url)
         ←image──
```

<a name="polling"></a>
## Polling Behaviour

| Setting | Default | Description |
|---------|---------|-------------|
| `poll_max_attempts` | 30 | Maximum number of status checks |
| `poll_interval_ms` | 3000 | Milliseconds between checks |

With defaults, the maximum wait time is ~90 seconds. If the task hasn't completed after all attempts, a `RuntimeException` is thrown.

**Error handling:**

- If the task status becomes `failed`, an exception is thrown immediately
- HTTP errors during polling are silently retried on the next attempt

<a name="size-formats"></a>
## Size Formats

Pass sizes in the standard `WxH` format:

```dotenv
OPENAI_IMAGE_SIZE=1024x1024
```

The provider parses this into separate width and height parameters for the Flux API.

<a name="limitations"></a>
## Limitations

- **Image only** — calling `chat()` throws a `RuntimeException`. Never set `AI_PROVIDER=piapi`.
- **Async** — generation takes 10-60 seconds depending on queue depth.
- **Rate limits** — governed by your PiAPI plan.

**Recommended setup:**

```dotenv
AI_PROVIDER=openai          # or claude, gemini, ollama
AI_IMAGE_PROVIDER=piapi     # images go through Flux
PIAPI_API_KEY=your-key
```
