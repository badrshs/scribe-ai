# Content Publisher

A pluggable Laravel package for AI-powered content processing and multi-channel publishing.

## Features

- **Pipeline Pattern** — Configurable content processing stages (scrape → AI rewrite → image generation → optimize → publish)
- **Strategy Pattern** — Swappable publisher drivers (Facebook, Telegram, Blogger, WordPress, Log)
- **Manager Pattern** — Runtime driver registration via `extend()`, just like Laravel's Cache/Queue
- **AI Services** — OpenAI-powered content rewriting, SEO suggestions, and image generation
- **Queue Support** — Background processing with configurable queues and overlap protection

## Installation

```bash
composer require bader/content-publisher
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
    ↓
ContentPipeline → [ScrapeStage → AiRewriteStage → GenerateImageStage → OptimizeImageStage → CreateArticleStage → PublishStage]
    ↓
PublisherManager → driver('facebook') → FacebookDriver::publish()
    ↓
PublishResult (DTO) → PublishLog (audit)
```

## Built-in Drivers

| Driver | Platform | Auth |
|--------|----------|------|
| `log` | Laravel Log | None |
| `facebook` | Facebook Pages | Page Access Token |
| `telegram` | Telegram Bot | Bot Token |
| `blogger` | Google Blogger | OAuth2 Service Account |
| `wordpress` | WordPress REST API | Application Passwords |

## License

MIT
