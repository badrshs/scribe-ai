<p align="center">
  <img src="https://img.shields.io/github/v/tag/badrshs/scribe-ai?label=version&style=flat-square" alt="Version">
  <img src="https://img.shields.io/packagist/php-v/badrshs/scribe-ai?style=flat-square" alt="PHP Version">
  <img src="https://img.shields.io/github/license/badrshs/scribe-ai?style=flat-square" alt="License">
</p>

# Scribe AI

**A Laravel package that turns any URL into a published article — automatically.**

Scribe AI scrapes a webpage, rewrites the content with AI, generates a cover image, optimises it for the web, saves the article to your database, and publishes it to one or more channels. One command. Zero manual steps.

> **Built for Laravel 11 & 12** · **PHP 8.2+** · **Queue-first** · **Fully extensible**

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Artisan Commands](#artisan-commands)
  - [Programmatic API](#programmatic-api)
  - [Custom Pipeline Stages](#custom-pipeline-stages)
  - [Custom Publish Drivers](#custom-publish-drivers)
- [Built-in Publish Drivers](#built-in-publish-drivers)
- [Architecture](#architecture)
- [License](#license)

---

## Installation

```bash
composer require badrshs/scribe-ai
```

Publish the config file and migrations, then migrate:

```bash
php artisan vendor:publish --tag=scribe-ai-config
php artisan vendor:publish --tag=scribe-ai-migrations
php artisan migrate
```

---

## Quick Start

Add your OpenAI key to `.env`:

```env
OPENAI_API_KEY=sk-...
```

Run the pipeline on any URL:

```bash
php artisan scribe:process-url https://example.com/article --sync
```

That's it. The article is scraped, rewritten, illustrated, stored, and published to the `log` channel by default. Swap `log` for real channels when you're ready.

---

## How It Works

Every URL passes through an ordered **pipeline** of stages. Each stage reads from an immutable `ContentPayload` DTO and passes a new copy to the next stage.

| # | Stage | What it does |
|---|-------|-------------|
| 1 | **Scrape** | Extracts title, body, and metadata from the source URL |
| 2 | **AI Rewrite** | Sends the raw content to OpenAI and returns a polished article |
| 3 | **Generate Image** | Creates a cover image with DALL-E based on article context |
| 4 | **Optimise Image** | Resizes, compresses, and converts the image to WebP |
| 5 | **Create Article** | Persists the article to the database with status, tags, and category |
| 6 | **Publish** | Pushes the article to every active publishing channel |

Stages are individually **skippable**, **replaceable**, and **reorderable** via config or at runtime.

---

## Configuration

All config lives under `config/scribe-ai.php`. Key environment variables:

```env
# -- AI ------------------------------------------------
OPENAI_API_KEY=sk-...
OPENAI_CONTENT_MODEL=gpt-4o-mini        # model for rewriting
OPENAI_IMAGE_MODEL=dall-e-3             # model for image generation

# -- Publishing ----------------------------------------
PUBLISHER_CHANNELS=log                  # comma-separated active channels
PUBLISHER_DEFAULT_CHANNEL=log

# -- Facebook ------------------------------------------
FACEBOOK_PAGE_ID=
FACEBOOK_PAGE_ACCESS_TOKEN=

# -- Telegram ------------------------------------------
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=

# -- Google Blogger ------------------------------------
BLOGGER_BLOG_ID=
GOOGLE_APPLICATION_CREDENTIALS=

# -- WordPress -----------------------------------------
WORDPRESS_URL=
WORDPRESS_USERNAME=
WORDPRESS_PASSWORD=
```

---

## Usage

### Artisan Commands

```bash
# Process a URL (queued by default)
php artisan scribe:process-url https://example.com/article

# Process synchronously (no queue)
php artisan scribe:process-url https://example.com/article --sync

# Publish an existing article by ID
php artisan scribe:publish 1

# Publish to specific channels only
php artisan scribe:publish 1 --channels=facebook,telegram

# Batch-publish approved staged content
php artisan scribe:publish-approved --limit=5
```

### Programmatic API

```php
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Facades\ContentPipeline;
use Bader\ContentPublisher\Facades\Publisher;

// Run the full pipeline
$payload = ContentPipeline::process(
    ContentPayload::fromUrl('https://example.com/article')
);

// Publish to a single channel
Publisher::driver('telegram')->publish($article);

// Publish to all active channels
Publisher::publishToChannels($article);
```

### Custom Pipeline Stages

Create a class that implements `Bader\ContentPublisher\Contracts\Pipe`:

```php
use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Closure;

class TranslateStage implements Pipe
{
    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        $translated = MyTranslator::translate($payload->content);

        return $next($payload->with(['content' => $translated]));
    }
}
```

Then use it at runtime or register it in the config:

```php
ContentPipeline::through([
    ScrapeStage::class,
    TranslateStage::class,
    CreateArticleStage::class,
])->process($payload);
```

### Custom Publish Drivers

Implement `Bader\ContentPublisher\Contracts\Publisher` and register the driver in a service provider:

```php
use Bader\ContentPublisher\Facades\Publisher;

Publisher::extend('medium', fn (array $config) => new MediumDriver($config));
```

Then add `medium` to your `PUBLISHER_CHANNELS` env variable.

---

## Built-in Publish Drivers

| Driver | Platform | Auth Method |
|--------|----------|-------------|
| `log` | Laravel Log *(dev / testing)* | None |
| `facebook` | Facebook Pages | Page Access Token |
| `telegram` | Telegram Bot API | Bot Token |
| `blogger` | Google Blogger | OAuth 2 Service Account |
| `wordpress` | WordPress REST API | Application Password |

---

## Architecture

```
+-------------------------------------------------------------------+
|                        ContentPipeline                             |
|                                                                   |
|  ContentPayload --> Stage 1 --> Stage 2 --> ... --> Stage N        |
|       (DTO)         Scrape     Rewrite          Publish            |
+-------------------------------------------------------------------+
                                                       |
                                                       v
+-------------------------------------------------------------------+
|                       PublisherManager                             |
|                                                                   |
|  driver('facebook') --> FacebookDriver::publish()                  |
|  driver('telegram') --> TelegramDriver::publish()                  |
|                                                                   |
|  Each result --> PublishResult DTO --> publish_logs table           |
+-------------------------------------------------------------------+
```

**Key classes:**

| Class | Role |
|-------|------|
| `ContentPayload` | Immutable DTO carrying state between stages |
| `ContentPipeline` | Orchestrates the stage sequence via Laravel Pipeline |
| `PublisherManager` | Resolves and dispatches to channel drivers |
| `PublishResult` | Per-channel outcome DTO, auto-persisted to `publish_logs` |

---

## License

MIT — see [LICENSE](https://github.com/badrshs/scribe-ai/blob/master/LICENSE) for details.
