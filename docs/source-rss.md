# RSS Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [Feed Formats](#feed-formats)
- [How It Works](#how-it-works)
- [Auto-Detection](#auto-detection)
- [Multiple Entries](#multiple-entries)

<a name="overview"></a>
## Overview

The RSS driver parses RSS 2.0 and Atom feeds, extracting the latest entry's content and title. It is commonly used with the [Telegram Approval](/docs/1.0/extension-telegram-approval) extension for feed-based content workflows.

<a name="configuration"></a>
## Configuration

```dotenv
RSS_TIMEOUT=30
RSS_MAX_ITEMS=10
```

Config in `config/scribe-ai.php`:

```php
'sources' => [
    'drivers' => [
        'rss' => [
            'timeout'   => (int) env('RSS_TIMEOUT', 30),
            'max_items' => (int) env('RSS_MAX_ITEMS', 10),
        ],
    ],
],
```

<a name="feed-formats"></a>
## Feed Formats

The driver supports:

| Format | Detection | Example |
|--------|-----------|---------|
| **RSS 2.0** | `<channel><item>` elements | Most WordPress, Blogger feeds |
| **Atom** | `<entry>` elements | GitHub, YouTube feeds |

Content is extracted from `<content:encoded>`, `<description>` (RSS) or `<content>`, `<summary>` (Atom).

<a name="how-it-works"></a>
## How It Works

1. **HTTP fetch** — downloads the feed XML
2. **XML parsing** — parses with `SimpleXMLElement`
3. **Entry extraction** — parses up to `max_items` entries
4. **Return latest** — the most recent entry is returned as primary content

**Response:**

```php
[
    'content' => 'Latest entry content (HTML tags preserved)...',
    'title'   => 'Entry Title',
    'meta'    => [
        'source_driver' => 'rss',
        'url'           => 'https://blog.com/feed.xml',
        'entry_link'    => 'https://blog.com/post-123',
        'entry_date'    => 'Mon, 01 Jan 2024 12:00:00 GMT',
        'entries'       => [ /* all parsed entries */ ],
    ],
]
```

The `entries` array in metadata contains all parsed entries (up to `max_items`), each with `title`, `content`, `link`, and `date`.

<a name="auto-detection"></a>
## Auto-Detection

The RSS driver matches URLs containing common feed patterns:

```php
public function supports(string $identifier): bool
{
    // Must be a valid URL
    // Path must match: feed, rss, atom, .xml, .rss, .atom
    return preg_match('#(feed|rss|atom|\.xml|\.rss|\.atom)#i', $path);
}
```

**Examples that match:**
- `https://blog.com/feed`
- `https://blog.com/rss.xml`
- `https://blog.com/atom.xml`
- `https://example.com/feed/posts`

**Force RSS for non-standard URLs:**

```bash
php artisan scribe:process-url https://blog.com/posts --source=rss
```

<a name="multiple-entries"></a>
## Multiple Entries

While the pipeline processes only the latest entry by default, all parsed entries are available in `meta.entries`. This is used by the Telegram Approval extension to send multiple entries for review:

```php
$result = app(ContentSourceManager::class)->fetch($feedUrl, 'rss');

foreach ($result['meta']['entries'] as $entry) {
    // Send each entry for approval
}
```
