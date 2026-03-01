# Web Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [HTML Cleaning](#html-cleaning)
- [Auto-Detection](#auto-detection)

<a name="overview"></a>
## Overview

The Web driver fetches HTML from any URL and strips it down to clean, readable content. It removes scripts, styles, navigation, footers, forms, and iframes — leaving only the meaningful text and structural tags.

<a name="configuration"></a>
## Configuration

```dotenv
WEB_SCRAPER_TIMEOUT=30
WEB_SCRAPER_USER_AGENT="Mozilla/5.0 (compatible; ContentBot/1.0)"
```

Config in `config/scribe-ai.php`:

```php
'sources' => [
    'drivers' => [
        'web' => [
            'timeout'    => (int) env('WEB_SCRAPER_TIMEOUT', 30),
            'user_agent' => env('WEB_SCRAPER_USER_AGENT', 'Mozilla/5.0 (compatible; ContentBot/1.0)'),
        ],
    ],
],
```

<a name="how-it-works"></a>
## How It Works

1. **HTTP request** — sends a GET request with configurable User-Agent, Accept, and Accept-Language headers
2. **HTML cleaning** — strips non-content elements via regex patterns
3. **Tag whitelist** — preserves `<p>`, `<br>`, headings, lists, `<blockquote>`, `<a>`, and `<img>`
4. **Whitespace normalisation** — collapses multiple spaces into one

**Response:**

```php
[
    'content' => 'cleaned HTML text...',
    'title'   => null,  // Not extracted by the web driver
    'meta'    => [
        'source_driver' => 'web',
        'url'           => 'https://example.com/article',
    ],
]
```

<a name="html-cleaning"></a>
## HTML Cleaning

The `WebScraper::clean()` method removes these elements:

| Element | Reason |
|---------|--------|
| `<script>` | JavaScript code |
| `<style>` | CSS stylesheets |
| `<nav>` | Navigation menus |
| `<footer>` | Page footers |
| `<header>` | Page headers |
| `<aside>` | Sidebars |
| `<form>` | Input forms |
| `<iframe>` | Embedded content |
| HTML comments | Developer annotations |

**Preserved tags:** `<p>`, `<br>`, `<h1>`–`<h6>`, `<ul>`, `<ol>`, `<li>`, `<blockquote>`, `<a>`, `<img>`

<a name="auto-detection"></a>
## Auto-Detection

The web driver matches any valid URL that isn't detected as an RSS feed first:

```php
public function supports(string $identifier): bool
{
    return filter_var($identifier, FILTER_VALIDATE_URL) !== false;
}
```

Since RSS is checked before web in the detection order, feed URLs are correctly routed to the RSS driver.
