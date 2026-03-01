# Content Sources — Input Drivers

---

- [Overview](#overview)
- [Built-in Drivers](#built-in-drivers)
- [Auto-Detection](#auto-detection)
- [ContentSourceManager](#content-source-manager)
- [Forcing a Driver](#forcing-a-driver)
- [Driver Response Format](#response-format)

<a name="overview"></a>
## Overview

Content sources are the **input side** of the pipeline. Each driver knows how to fetch raw content from a particular medium — a web page, an RSS feed, or raw text. The `ContentSourceManager` resolves the right driver automatically or lets you force one explicitly.

<a name="built-in-drivers"></a>
## Built-in Drivers

| Driver | Description | Identifier | Auto-Detect |
|--------|-------------|-----------|-------------|
| [Web](/docs/1.0/source-web) | Scrapes and cleans HTML from URLs | Any valid URL | ✅ |
| [RSS](/docs/1.0/source-rss) | Parses RSS 2.0 / Atom feeds | Feed URL (`.xml`, `/feed`, `/rss`) | ✅ |
| [Text](/docs/1.0/source-text) | Accepts pre-fetched raw text | Any non-URL string | ✅ (fallback) |

<a name="auto-detection"></a>
## Auto-Detection

When the pipeline processes content, the `ScrapeStage` uses the `ContentSourceManager` to auto-detect the right driver:

**Detection order:** `rss` → custom drivers → `web` → `text`

1. If the identifier matches an RSS feed URL pattern → **RSS driver**
2. If any custom driver returns `supports() === true` → that driver
3. If the identifier is a valid URL → **Web driver**
4. If the identifier is not a URL → **Text driver** (catch-all)

You can skip auto-detection by forcing a driver:

```bash
php artisan scribe:process-url https://example.com/article --source=web
```

<a name="content-source-manager"></a>
## ContentSourceManager

The `ContentSourceManager` manages driver resolution, auto-detection, and extensibility.

**Key methods:**

| Method | Description |
|--------|-------------|
| `driver(?string $name)` | Resolve a driver by name |
| `fetch(string $identifier, ?string $forcedDriver)` | Auto-detect and fetch content |
| `extend(string $name, Closure $callback)` | Register a custom driver |
| `availableDrivers()` | List all driver names |
| `getDefaultDriver()` | Get the default driver from config |

**Basic usage:**

```php
use Badr\ScribeAi\Services\Sources\ContentSourceManager;

$manager = app(ContentSourceManager::class);

// Auto-detect
$result = $manager->fetch('https://example.com/article');

// Force a specific driver
$result = $manager->fetch('https://blog.com/feed.xml', 'rss');

// Get a specific driver
$driver = $manager->driver('web');
$result = $driver->fetch('https://example.com/article');
```

<a name="forcing-a-driver"></a>
## Forcing a Driver

Three ways to force a specific driver:

**1. CLI option:**

```bash
php artisan scribe:process-url https://example.com --source=rss
```

**2. Payload property:**

```php
$payload = ContentPayload::fromUrl($url)->with(['sourceDriver' => 'rss']);
```

**3. Direct call:**

```php
$result = app(ContentSourceManager::class)->fetch($url, 'rss');
```

<a name="response-format"></a>
## Driver Response Format

All drivers return a standardised array:

```php
[
    'content' => string,        // The raw/cleaned content
    'title'   => ?string,       // Extracted title (if available)
    'meta'    => [              // Driver-specific metadata
        'source_driver' => string,
        'url'           => ?string,
        // ... additional driver-specific keys
    ],
]
```

The `ScrapeStage` maps this to the payload's `rawContent`, `cleanedContent`, `title`, and `extra` fields.
