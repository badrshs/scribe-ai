# Ollama Provider

---

- [Overview](#overview)
- [Setup](#setup)
- [Configuration](#configuration)
- [Supported Models](#supported-models)
- [How It Works](#how-it-works)
- [JSON Mode](#json-mode)
- [Performance Tips](#performance-tips)

<a name="overview"></a>
## Overview

The Ollama provider connects to a local or self-hosted Ollama instance for fully offline AI processing. No API keys, no external calls — all data stays on your infrastructure.

<a name="setup"></a>
## Setup

1. **Install Ollama**: [https://ollama.com](https://ollama.com)
2. **Pull a model**:

```bash
ollama pull llama3.1
# or
ollama pull mistral
```

3. **Start Ollama** (runs on port 11434 by default):

```bash
ollama serve
```

<a name="configuration"></a>
## Configuration

```dotenv
AI_PROVIDER=ollama
OLLAMA_HOST=http://localhost:11434
OPENAI_CONTENT_MODEL=llama3.1
```

Provider-specific config in `config/scribe-ai.php`:

```php
'providers' => [
    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
    ],
],
```

<a name="supported-models"></a>
## Supported Models

Any model available in your Ollama instance:

- `llama3.1` / `llama3.1:70b` — Meta's Llama 3.1
- `mistral` / `mistral-nemo` — Mistral AI models
- `gemma2` — Google's Gemma 2
- `qwen2.5` — Alibaba's Qwen 2.5
- `phi3` — Microsoft's Phi-3
- `deepseek-coder` — Code-focused model

Run `ollama list` to see your installed models.

<a name="how-it-works"></a>
## How It Works

Ollama exposes an OpenAI-compatible API at `/api/chat`. The provider sends messages in the standard format with `stream: false` and normalizes the response.

**Key differences from OpenAI:**

- Token limits use `options.num_predict` instead of `max_tokens`
- Timeout is set to 300s (local inference can be slow on CPU)
- No API key required

<a name="json-mode"></a>
## JSON Mode

When `jsonMode: true` is requested, `format: 'json'` is added to the payload. Ollama enforces strict JSON output natively.

<a name="performance-tips"></a>
## Performance Tips

- **GPU acceleration** — Ollama auto-detects NVIDIA/AMD GPUs. Ensure drivers are installed.
- **Larger models = slower** — For the pipeline, `llama3.1:8b` or `mistral` are good balances of quality and speed.
- **Remote Ollama** — Point to another machine:

```dotenv
OLLAMA_HOST=http://192.168.1.100:11434
```

- **Image provider** — Ollama doesn't support image generation. Set a separate image provider:

```dotenv
AI_PROVIDER=ollama
AI_IMAGE_PROVIDER=openai
```
