# Configuration

---

- [Config File](#config-file)
- [Environment Variables](#environment-variables)
- [AI Configuration](#ai-configuration)
- [Pipeline Configuration](#pipeline-configuration)
- [Publishing Configuration](#publishing-configuration)
- [Image Configuration](#image-configuration)
- [Source Configuration](#source-configuration)
- [Queue Configuration](#queue-configuration)

<a name="config-file"></a>
## Config File

All configuration lives under `config/scribe-ai.php`. Publish it with:

```bash
php artisan vendor:publish --tag=scribe-ai-config
```

<a name="environment-variables"></a>
## Environment Variables

Here is the complete list of environment variables:

```env
# ── AI Provider ──────────────────────────────────────
AI_PROVIDER=openai                      # openai, claude, gemini, ollama
AI_IMAGE_PROVIDER=                      # separate provider for images (optional)
AI_OUTPUT_LANGUAGE=English              # language for AI-written articles

# ── OpenAI ───────────────────────────────────────────
OPENAI_API_KEY=sk-...
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_CONTENT_MODEL=gpt-4o-mini
OPENAI_FALLBACK_MODEL=gpt-4o-mini
OPENAI_IMAGE_MODEL=dall-e-3
OPENAI_IMAGE_SIZE=1024x1024
OPENAI_IMAGE_QUALITY=standard
OPENAI_MAX_TOKENS=4096

# ── Anthropic Claude ─────────────────────────────────
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_BASE_URL=https://api.anthropic.com/v1
ANTHROPIC_API_VERSION=2023-06-01

# ── Google Gemini ────────────────────────────────────
GEMINI_API_KEY=AIza...
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta

# ── Ollama (local) ───────────────────────────────────
OLLAMA_HOST=http://localhost:11434

# ── PiAPI (Flux) ─────────────────────────────────────
PIAPI_API_KEY=
PIAPI_BASE_URL=https://api.piapi.ai
PIAPI_POLL_MAX_ATTEMPTS=30
PIAPI_POLL_INTERVAL_MS=3000

# ── Pipeline ─────────────────────────────────────────
PIPELINE_HALT_ON_ERROR=true
PIPELINE_TRACK_RUNS=true

# ── Content Sources ──────────────────────────────────
CONTENT_SOURCE_DRIVER=web
WEB_SCRAPER_TIMEOUT=30
WEB_SCRAPER_USER_AGENT="Mozilla/5.0 (compatible; ContentBot/1.0)"
RSS_TIMEOUT=30
RSS_MAX_ITEMS=10

# ── Images ───────────────────────────────────────────
IMAGE_OPTIMIZE=true
IMAGE_MAX_WIDTH=1600
IMAGE_QUALITY=82

# ── Publishing ───────────────────────────────────────
PUBLISHER_CHANNELS=log
PUBLISHER_DEFAULT_CHANNEL=log

# ── Facebook ─────────────────────────────────────────
FACEBOOK_PAGE_ID=
FACEBOOK_PAGE_ACCESS_TOKEN=
FACEBOOK_API_VERSION=v21.0

# ── Telegram ─────────────────────────────────────────
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
TELEGRAM_PARSE_MODE=HTML

# ── Blogger ──────────────────────────────────────────
BLOGGER_BLOG_ID=
BLOGGER_API_KEY=
GOOGLE_APPLICATION_CREDENTIALS=

# ── WordPress ────────────────────────────────────────
WORDPRESS_URL=
WORDPRESS_USERNAME=
WORDPRESS_PASSWORD=
WORDPRESS_DEFAULT_STATUS=publish

# ── Queue ────────────────────────────────────────────
PIPELINE_QUEUE=pipeline
PUBLISHING_QUEUE=publishing

# ── Telegram Approval Extension ──────────────────────
TELEGRAM_APPROVAL_ENABLED=false
TELEGRAM_APPROVAL_BOT_TOKEN=
TELEGRAM_APPROVAL_CHAT_ID=
TELEGRAM_WEBHOOK_URL=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_WEBHOOK_PATH=api/scribe/telegram/webhook
```

<a name="ai-configuration"></a>
## AI Configuration

The `ai` section in `config/scribe-ai.php` controls which AI provider is used for text rewriting and image generation.

```php
'ai' => [
    'provider'       => env('AI_PROVIDER', 'openai'),
    'image_provider' => env('AI_IMAGE_PROVIDER'),
    'content_model'  => env('OPENAI_CONTENT_MODEL', 'gpt-4o-mini'),
    'output_language' => env('AI_OUTPUT_LANGUAGE', 'English'),

    'providers' => [
        'openai'  => ['api_key' => env('OPENAI_API_KEY'), ...],
        'claude'  => ['api_key' => env('ANTHROPIC_API_KEY'), ...],
        'gemini'  => ['api_key' => env('GEMINI_API_KEY'), ...],
        'ollama'  => ['host' => env('OLLAMA_HOST', 'http://localhost:11434')],
        'piapi'   => ['api_key' => env('PIAPI_API_KEY'), ...],
    ],
],
```

See [AI Providers](/docs/1.0/ai-providers) for full details on each provider.

<a name="pipeline-configuration"></a>
## Pipeline Configuration

```php
'pipeline' => [
    'stages' => [
        ScrapeStage::class,
        AiRewriteStage::class,
        GenerateImageStage::class,
        OptimizeImageStage::class,
        CreateArticleStage::class,
        PublishStage::class,
    ],
    'halt_on_error' => (bool) env('PIPELINE_HALT_ON_ERROR', true),
    'track_runs'    => (bool) env('PIPELINE_TRACK_RUNS', true),
],
```

- **halt_on_error** — When `true`, the pipeline halts and rejects the payload if any stage throws. When `false`, failing stages log a warning and continue.
- **track_runs** — When `true`, each pipeline execution is persisted to `pipeline_runs` for resume capability.

You can add, remove, or reorder stages in this array.

<a name="publishing-configuration"></a>
## Publishing Configuration

Active channels are read from `PUBLISHER_CHANNELS` (comma-separated):

```env
PUBLISHER_CHANNELS=telegram,facebook,log
```

Each driver has its own config block under `drivers`. See [Publishing](/docs/1.0/publishing) for details.

<a name="image-configuration"></a>
## Image Configuration

```php
'images' => [
    'optimize'               => true,
    'max_width'              => 1600,
    'quality'                => 82,
    'format'                 => 'webp',
    'min_size_for_conversion' => 20480,
    'directory'              => 'articles',
    'disk'                   => 'public',
],
```

<a name="source-configuration"></a>
## Source Configuration

```php
'sources' => [
    'default' => 'web',
    'drivers' => [
        'web'  => ['timeout' => 30, 'user_agent' => '...'],
        'rss'  => ['timeout' => 30, 'max_items' => 10],
        'text' => [],
    ],
],
```

<a name="queue-configuration"></a>
## Queue Configuration

Pipeline and publishing jobs use separate queues for independent scaling:

```php
'queue' => [
    'pipeline'   => env('PIPELINE_QUEUE', 'pipeline'),
    'publishing' => env('PUBLISHING_QUEUE', 'publishing'),
],
```

Run workers for both queues:

```bash
php artisan queue:work --queue=pipeline,publishing
```
