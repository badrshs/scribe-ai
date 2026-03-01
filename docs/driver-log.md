# Log Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [When To Use](#when-to-use)

<a name="overview"></a>
## Overview

The Log driver writes publish operations to Laravel's log instead of making external API calls. It always succeeds and supports all articles, making it the safest driver for development and testing.

<a name="configuration"></a>
## Configuration

```php
// config/scribe-ai.php
'drivers' => [
    'log' => [
        'driver'  => 'log',
        'level'   => 'info',     // Log level: debug, info, warning, etc.
        'channel' => null,       // Laravel log channel (null = default)
    ],
],
```

**Environment:**

```dotenv
PUBLISHER_CHANNELS=log
PUBLISHER_DEFAULT_CHANNEL=log
```

**Log output example:**

```
[2024-01-15 10:30:00] local.INFO: Article published (log driver) {
    "article_id": 42,
    "title": "10 Tips for Better Productivity",
    "slug": "10-tips-for-better-productivity",
    "status": "published",
    "options": []
}
```

<a name="when-to-use"></a>
## When To Use

- **Development** — verify the pipeline works end-to-end without external services
- **Testing** — the log driver is the default for test suites
- **Debugging** — add `log` alongside real channels to log all publish attempts

```dotenv
# Log alongside real channels
PUBLISHER_CHANNELS=log,telegram,facebook
```
