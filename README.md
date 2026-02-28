# Scribe AI

[![Latest Version](https://img.shields.io/github/v/tag/badrshs/scribe-ai?label=version)](https://github.com/badrshs/scribe-ai/releases)
[![License](https://img.shields.io/github/license/badrshs/scribe-ai)](https://github.com/badrshs/scribe-ai/blob/master/LICENSE)

**Scribe AI** is a Laravel package that acts as a fully autonomous content agent — give it a URL, and it handles everything else: scraping the source, rewriting the content with AI, generating and optimizing a cover image, storing the article, and pushing it live to one or more publishing channels, all in a single pipeline run.

It was built around one core idea: **content should flow from raw input to published output without manual intervention.** Every step is a composable, swappable stage. Every output is logged. Every channel is a pluggable driver.

### What it does, step by step

**1. Scrape**
Point it at any URL. The scraper extracts the title, body text, and meta from the page — no boilerplate, no noise.

**2. AI Rewrite**
The raw content is passed to OpenAI. The AI rewrites it into a clean, well-structured article — with a proper tone, flow, and length — ready for publication.

**3. Generate Image**
No image? No problem. The AI generates a relevant cover image from the article context using DALL-E (or your own image generation backend).

**4. Optimize Image**
The generated (or existing) image is automatically resized, compressed, and stored — ready for web delivery without you touching a thing.

**5. Create Article**
The rewritten content and optimized image are saved as a structured `Article` model in your Laravel app, with status tracking, category, tags, and full audit trail.

**6. Publish**
The article is pushed to every configured publishing channel simultaneously — Facebook Page, Telegram Channel, Google Blogger, WordPress, or your own custom driver. Each publish is logged with its result.

---

```
[ URL ]
   |
   v
[ Scrape ]  ->  extract title, body, metadata from any webpage
   |
   v
[ AI Rewrite ]  ->  GPT rewrites content into a polished article
   |
   v
[ Generate Image ]  ->  DALL-E creates a relevant cover image
   |
   v
[ Optimize Image ]  ->  resize, compress, store for web
   |
   v
[ Create Article ]  ->  saved to your DB with full audit fields
   |
   v
[ Publish ]  ->  pushed to Facebook, Telegram, Blogger, WordPress, ...
```

The entire flow runs in a **queued background job** — fire and forget. Or run it synchronously if you need it inline. Stages are individually swappable; drop one out, add your own, reorder them — the pipeline adapts.


## Installation

```bash
composer require badrshs/scribe-ai
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=content-publisher-config
php artisan vendor:publish --tag=content-publisher-migrations
php artisan migrate
```

## Configuration

Set your environment variables:

```env
# AI (required for content processing)
OPENAI_API_KEY=your-key

# Publisher channels (comma-separated)
PUBLISHER_CHANNELS=log
PUBLISHER_DEFAULT_CHANNEL=log

# Facebook
FACEBOOK_PAGE_ID=
FACEBOOK_PAGE_ACCESS_TOKEN=

# Telegram
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=

# Google Blogger
BLOGGER_BLOG_ID=
GOOGLE_APPLICATION_CREDENTIALS=

# WordPress
WORDPRESS_URL=
WORDPRESS_USERNAME=
WORDPRESS_PASSWORD=
```

## Usage

### Process a URL through the pipeline

```bash
php artisan content:process-url https://example.com/article
php artisan content:process-url https://example.com/article --sync
```

### Publish an article

```bash
php artisan content:publish 1
php artisan content:publish 1 --channels=facebook,telegram
```

### Publish approved staged content

```bash
php artisan content:publish-approved --limit=5
```

### Programmatic usage

```php
use Bader\ContentPublisher\Facades\Publisher;
use Bader\ContentPublisher\Facades\ContentPipeline;
use Bader\ContentPublisher\Data\ContentPayload;

// Process content through the pipeline
$result = ContentPipeline::process(ContentPayload::fromUrl('https://example.com'));

// Publish to a specific channel
Publisher::driver('facebook')->publish($article);

// Publish to all active channels
Publisher::publishToChannels($article);
```

### Register a custom driver

```php
use Bader\ContentPublisher\Facades\Publisher;

Publisher::extend('medium', function (array $config) {
    return new MediumDriver($config);
});
```

### Customize pipeline stages

```php
use Bader\ContentPublisher\Facades\ContentPipeline;

ContentPipeline::through([
    ScrapeStage::class,
    MyCustomStage::class,
    CreateArticleStage::class,
])->process($payload);
```

## Architecture

```
ContentPayload (DTO)
    |
ContentPipeline -> [ScrapeStage -> AiRewriteStage -> GenerateImageStage -> OptimizeImageStage -> CreateArticleStage -> PublishStage]
    |
PublisherManager -> driver('facebook') -> FacebookDriver::publish()
    |
PublishResult (DTO) -> PublishLog (audit)
```

## Built-in Drivers

| Driver    | Platform             | Auth                    |
|-----------|----------------------|-------------------------|
| `log`     | Laravel Log          | None                    |
| `facebook`| Facebook Pages       | Page Access Token       |
| `telegram`| Telegram Bot         | Bot Token               |
| `blogger` | Google Blogger       | OAuth2 Service Account  |
| `wordpress`| WordPress REST API  | Application Passwords   |

## License

MIT -- see [LICENSE](https://github.com/badrshs/scribe-ai/blob/master/LICENSE)
