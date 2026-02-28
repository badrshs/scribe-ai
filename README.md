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
- [Categories](#categories)
- [Content Sources (Input Drivers)](#content-sources-input-drivers)
- [Run Tracking & Resume](#run-tracking--resume)
- [Image Optimization](#image-optimization)
- [Built-in Publish Drivers](#built-in-publish-drivers)
- [Architecture](#architecture)
- [Extensions](#extensions)
  - [Telegram Approval (RSS → AI → Telegram → Pipeline)](#telegram-approval-rss--ai--telegram--pipeline)
- [Testing](#testing)
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
AI_OUTPUT_LANGUAGE=English              # language for AI-written articles

# -- Pipeline ------------------------------------------
PIPELINE_HALT_ON_ERROR=true             # stop on stage failure (default)
PIPELINE_TRACK_RUNS=true                # persist each run for resume support

# -- Content Sources -----------------------------------
CONTENT_SOURCE_DRIVER=web               # default input driver (web, rss, text)
WEB_SCRAPER_TIMEOUT=30
RSS_MAX_ITEMS=10

# -- Image ---------------------------------------------
IMAGE_OPTIMIZE=true                     # set false to skip WebP conversion

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

# -- Telegram Approval Extension -----------------------
TELEGRAM_APPROVAL_ENABLED=false         # enable the RSS→Telegram workflow
TELEGRAM_APPROVAL_BOT_TOKEN=            # defaults to TELEGRAM_BOT_TOKEN
TELEGRAM_APPROVAL_CHAT_ID=              # defaults to TELEGRAM_CHAT_ID
TELEGRAM_WEBHOOK_URL=                   # set for webhook mode
TELEGRAM_WEBHOOK_SECRET=                # optional verification secret
```

---

## Usage

### Artisan Commands

```bash
# Process a URL (queued by default)
php artisan scribe:process-url https://example.com/article

# Process synchronously with live progress output
php artisan scribe:process-url https://example.com/article --sync

# Pass categories inline (id:name pairs)
php artisan scribe:process-url https://example.com/article --sync --categories="1:Tech,2:Health,3:Business"

# Force a specific source driver (auto-detected by default)
php artisan scribe:process-url https://blog.com/feed.xml --sync --source=rss

# Suppress progress output
php artisan scribe:process-url https://example.com/article --sync --silent

# List recent pipeline runs
php artisan scribe:runs
php artisan scribe:runs --status=failed

# Resume a failed run (picks up from the failed stage)
php artisan scribe:resume 42

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
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline as Pipeline;

// Run the full pipeline
$payload = ContentPipeline::process(
    ContentPayload::fromUrl('https://example.com/article')
);

// Pass categories via the payload
$payload = new ContentPayload(
    sourceUrl: 'https://example.com/article',
    categories: [1 => 'Technology', 2 => 'Health', 3 => 'Business'],
);
$result = app(Pipeline::class)->process($payload);

// Resume a failed run
$result = app(Pipeline::class)->resume($pipelineRunId);

// Disable run tracking for a one-off call
$result = app(Pipeline::class)->withoutTracking()->process($payload);

// Listen to progress events
app(Pipeline::class)
    ->onProgress(function (string $stage, string $status) {
        echo "{$stage}: {$status}\n";
    })
    ->process($payload);

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

## Categories

Categories are **fully optional**. If no categories are provided, the AI writes freely without category constraints.

When categories **are** provided, the AI selects the most appropriate one from the list and includes `category_id` in its JSON response.

### How categories are resolved

The pipeline resolves categories in priority order — the first non-empty source wins:

| Priority | Source | Example |
|----------|--------|---------|
| 1 | **Payload** — passed directly in code or CLI | `--categories="1:Tech,2:Health"` |
| 2 | **Database** — `categories` table | Rows seeded or added via your app |
| 3 | **Config** — `scribe-ai.categories` array | `[1 => 'Tech', 2 => 'Health']` |
| 4 | **None** — empty list | AI writes without category selection |

### Passing categories

**CLI:**
```bash
php artisan scribe:process-url https://example.com --sync --categories="1:Tech,2:Health,3:Business"
```

**Programmatic:**
```php
$payload = new ContentPayload(
    sourceUrl: 'https://example.com/article',
    categories: [1 => 'Technology', 2 => 'Health', 3 => 'Business'],
);
app(Pipeline::class)->process($payload);
```

**Config** (`config/scribe-ai.php`):
```php
'categories' => [
    1 => 'Technology',
    2 => 'Health',
    3 => 'Business',
],
```

---

## Content Sources (Input Drivers)

The **input** side of the pipeline uses the same extensible driver pattern as publishing. `ContentSourceManager` resolves a content-source driver for each identifier (URL, feed, raw text) — either by **auto-detection** or by explicit override.

```
Input:      ContentSourceManager  → web, rss, text, your custom drivers
Processing: ContentPipeline       → scrape, rewrite, image, publish, ...
Output:     PublisherManager      → log, telegram, facebook, ...
```

### Built-in source drivers

| Driver | Identifier | What it does |
|--------|-----------|-------------|
| `web` | Any HTTP(S) URL | Scrapes and cleans the HTML content |
| `rss` | Feed URL (`.xml`, `.rss`, `/feed`) | Parses RSS 2.0 / Atom, returns latest entry |
| `text` | Any non-URL string | Passes raw text straight through (no network call) |

### Auto-detection vs explicit override

By default the manager iterates drivers in order (`rss → web → text`) and picks the first one whose `supports()` returns true. You can force a specific driver instead:

**CLI:**
```bash
# Auto-detect (URL → web driver)
php artisan scribe:process-url https://example.com/article --sync

# Force RSS driver
php artisan scribe:process-url https://blog.com/feed.xml --sync --source=rss

# Force text driver (pipe content in via payload)
```

**Programmatic:**
```php
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;

// Auto-detect
$payload = ContentPayload::fromUrl('https://blog.com/feed.xml');
app(ContentPipeline::class)->process($payload);

// Force a specific driver
$payload = new ContentPayload(
    sourceUrl: 'https://blog.com/feed.xml',
    sourceDriver: 'rss',
);
app(ContentPipeline::class)->process($payload);
```

**Fetch content without the pipeline:**
```php
use Bader\ContentPublisher\Facades\ContentSource;

// Auto-detect
$result = ContentSource::fetch('https://example.com/article');
// $result = ['content' => '...', 'title' => '...', 'meta' => [...]]

// Force driver
$result = ContentSource::driver('rss')->fetch('https://blog.com/feed.xml');
```

### Registering custom source drivers

Create a class implementing `Bader\ContentPublisher\Contracts\ContentSource`:

```php
use Bader\ContentPublisher\Contracts\ContentSource;

class YouTubeTranscriptSource implements ContentSource
{
    public function __construct(protected array $config = []) {}

    public function fetch(string $identifier): array
    {
        // Fetch transcript from YouTube API...
        return ['content' => $transcript, 'title' => $videoTitle, 'meta' => [...]];
    }

    public function supports(string $identifier): bool
    {
        return str_contains($identifier, 'youtube.com') || str_contains($identifier, 'youtu.be');
    }

    public function name(): string
    {
        return 'youtube';
    }
}
```

Register it in a service provider:
```php
use Bader\ContentPublisher\Services\Sources\ContentSourceManager;

app(ContentSourceManager::class)->extend('youtube', fn(array $config) => new YouTubeTranscriptSource($config));
```

### Configuration

```env
# Default source driver (used when no auto-detection match)
CONTENT_SOURCE_DRIVER=web

# Web driver settings
WEB_SCRAPER_TIMEOUT=30
WEB_SCRAPER_USER_AGENT="Mozilla/5.0 (compatible; ContentBot/1.0)"

# RSS driver settings
RSS_TIMEOUT=30
RSS_MAX_ITEMS=10
```

---

## Run Tracking & Resume

Every pipeline execution is automatically persisted to the `pipeline_runs` table, giving you full visibility into what ran, what failed, and the ability to **resume from the exact stage that failed**.

### How it works

1. When `process()` starts, a `PipelineRun` record is created with status `Pending`.
2. As each stage completes, the run's `current_stage_index` and `payload_snapshot` are updated.
3. On success → status becomes `Completed`. On rejection → `Rejected`. On uncaught exception → `Failed` (with `error_message` and `error_stage` recorded).
4. Failed runs can be **resumed** — the pipeline rehydrates the payload from the last snapshot and continues from the failed stage.

### Listing runs

```bash
# Show the 20 most recent runs
php artisan scribe:runs

# Filter by status
php artisan scribe:runs --status=failed

# Show more
php artisan scribe:runs --limit=50
```

### Resuming a failed run

```bash
# Resume run #42 from the stage that failed
php artisan scribe:resume 42
```

**Programmatic:**
```php
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;

$pipeline = app(ContentPipeline::class);

// Resume by run ID
$result = $pipeline->resume(42);

// Or pass the PipelineRun model directly
$run = PipelineRun::find(42);
$result = $pipeline->resume($run);
```

### Disabling run tracking

Run tracking is enabled by default. To disable it:

```env
PIPELINE_TRACK_RUNS=false
```

Or disable it for a single call:
```php
app(ContentPipeline::class)->withoutTracking()->process($payload);
```

> **Note:** When tracking is enabled, the `pipeline_runs` migration must exist. If the table is missing, the pipeline throws a `RuntimeException` at startup rather than failing silently mid-run.

---

## Image Optimization

Generated cover images are automatically converted to **WebP** format with configurable quality and dimensions. This reduces file size while maintaining visual quality.

To **disable** image optimization (e.g., if you handle images externally):

```env
IMAGE_OPTIMIZE=false
```

When disabled, the `OptimizeImageStage` is silently skipped and the original image passes through unchanged.

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
|                     ContentSourceManager                           |
|                                                                    |
|  identifier --> auto-detect / forced driver                        |
|  driver('web')  --> WebDriver::fetch()                             |
|  driver('rss')  --> RssDriver::fetch()                             |
|  driver('text') --> TextDriver::fetch()                            |
+-------------------------------------------------------------------+
                          |
                          v
+-------------------------------------------------------------------+
|                        ContentPipeline                             |
|                                                                    |
|  ContentPayload --> Stage 1 --> Stage 2 --> ... --> Stage N        |
|       (DTO)         Scrape     Rewrite          Publish            |
|                                                                    |
|  Each stage tracked in PipelineRun (DB)                            |
|  Failed? → snapshot saved → resume from that stage                 |
+-------------------------------------------------------------------+
                          |
                          v
+-------------------------------------------------------------------+
|                       PublisherManager                             |
|                                                                    |
|  driver('facebook') --> FacebookDriver::publish()                  |
|  driver('telegram') --> TelegramDriver::publish()                  |
|                                                                    |
|  Each result --> PublishResult DTO --> publish_logs table           |
+-------------------------------------------------------------------+
```

**Key classes:**

| Class | Role |
|-------|------|
| `ContentSourceManager` | Resolves input drivers (web, rss, text, custom). Auto-detects or uses explicit override. |
| `ContentPayload` | Immutable DTO carrying state between stages. Supports `toSnapshot()` / `fromSnapshot()` for JSON serialisation. |
| `ContentPipeline` | Runs stages in sequence, tracks each step in a `PipelineRun`, supports resume from failure. |
| `PipelineRun` | Eloquent model persisting run state, stage progress, and payload snapshots to `pipeline_runs`. |
| `PublisherManager` | Resolves and dispatches to channel publish drivers. |
| `PublishResult` | Per-channel outcome DTO, auto-persisted to `publish_logs`. |

---

## Extensions

Extensions are optional modules that add complete workflows on top of the core pipeline. Each extension is loaded only when explicitly enabled, keeping the default footprint minimal.

### Telegram Approval (RSS → AI → Telegram → Pipeline)

A two-phase human-in-the-loop workflow:

```
Phase 1:  RSS feed → AI analysis → Telegram messages with ✅/❌ buttons → StagedContent (pending)
Phase 2:  Human approves → pipeline dispatched with web driver → Article created & published
```

#### Enable the extension

```env
TELEGRAM_APPROVAL_ENABLED=true

# Uses the Telegram publish driver's bot_token/chat_id by default.
# Override if you want a separate bot for approvals:
TELEGRAM_APPROVAL_BOT_TOKEN=
TELEGRAM_APPROVAL_CHAT_ID=
```

#### Phase 1 — Fetch RSS & send for review

```bash
# Fetch RSS, filter entries from the last 7 days, send to Telegram
php artisan scribe:rss-review https://blog.com/feed.xml

# Use AI to summarise and rank entries, filter older than 3 days
php artisan scribe:rss-review https://blog.com/feed.xml --days=3 --ai-filter

# Limit to 5 entries
php artisan scribe:rss-review https://blog.com/feed.xml --limit=5 --ai-filter
```

Each entry appears in your Telegram chat with:
- Title, category, AI summary (when `--ai-filter` is used)
- Source URL
- **✅ Approve** / **❌ Reject** inline buttons

Entries are stored as `StagedContent` (pending). The pipeline does **not** run yet.

#### Phase 2 — Process decisions

**Option A: Polling** (no webhook needed, works locally)
```bash
# Continuous long-poll (Ctrl+C to stop)
php artisan scribe:telegram-poll

# Single pass — process pending decisions and exit
php artisan scribe:telegram-poll --once
```

**Option B: Webhook** (production — Telegram pushes decisions to your app)
```env
TELEGRAM_WEBHOOK_URL=https://yourapp.com/api/scribe/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=your-random-secret
```
```bash
php artisan scribe:telegram-set-webhook
```

When you tap **✅ Approve** in Telegram:
1. The `StagedContent` is marked as approved
2. The full pipeline is dispatched using the **web** driver (URL already known)
3. Article is created, optimised, and published to your configured channels

When you tap **❌ Reject**, the entry is marked as processed and skipped.

#### Extension file structure

All extension code lives in a self-contained directory:

```
src/Extensions/TelegramApproval/
    TelegramApprovalService.php     # Telegram Bot API interactions
    CallbackHandler.php             # Processes approve/reject decisions
    RssReviewCommand.php            # scribe:rss-review
    TelegramPollCommand.php         # scribe:telegram-poll
    SetWebhookCommand.php           # scribe:telegram-set-webhook
    TelegramWebhookController.php   # HTTP controller for webhook
routes/
    telegram-webhook.php            # Webhook route definition
```

---

## Testing

The package ships with **22 unit tests** (63 assertions) using [Orchestra Testbench](https://packages.tools/testbench).

```bash
# Run all unit/feature tests
./vendor/bin/phpunit

# Run a specific test
./vendor/bin/phpunit --filter=test_full_pipeline_end_to_end
```

### Integration tests (real OpenAI API)

Integration tests that call the real OpenAI API are excluded from the default test suite. To run them:

1. Copy `.env.testing.example` to `.env.testing` and set your real API key:
   ```env
   OPENAI_API_KEY=sk-your-real-key
   ```

2. Run only integration tests:
   ```bash
   ./vendor/bin/phpunit --group=integration
   ```

> Integration tests are grouped with `#[Group('integration')]` and skipped automatically when no real API key is present.

---

## License

MIT — see [LICENSE](https://github.com/badrshs/scribe-ai/blob/master/LICENSE) for details.
