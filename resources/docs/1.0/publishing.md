# Publisher Manager

---

- [Overview](#overview)
- [Active Channels](#active-channels)
- [Publishing Flow](#publishing-flow)
- [PublisherManager API](#publisher-manager-api)
- [Publish Logs](#publish-logs)
- [Duplicate Prevention](#duplicate-prevention)

<a name="overview"></a>
## Overview

The `PublisherManager` is a Strategy + Manager pattern that routes articles to one or more publishing channels. Each channel is backed by a driver (Log, Telegram, Facebook, Blogger, WordPress, or custom).

<a name="active-channels"></a>
## Active Channels

Active channels are configured via the `PUBLISHER_CHANNELS` env var (comma-separated):

```dotenv
PUBLISHER_CHANNELS=telegram,facebook,wordpress
```

Only channels listed here receive content when `publishToChannels()` is called.

**Default channel** (for single-channel operations):

```dotenv
PUBLISHER_DEFAULT_CHANNEL=log
```

<a name="publishing-flow"></a>
## Publishing Flow

When `publishToChannels()` is called:

1. **Iterate** each active channel
2. **Check support** — `driver->supports($article)` (most drivers require `isPublished()`)
3. **Check duplicates** — `article->wasPublishedTo($channel)` skips re-publishing
4. **Publish** — calls `driver->publish($article)`
5. **Log result** — success or failure is persisted to `publish_logs`

```php
use Bader\ContentPublisher\Services\Publishing\PublisherManager;

$publisher = app(PublisherManager::class);

// Publish to all active channels
$results = $publisher->publishToChannels($article);

// Publish to specific channels only
$results = $publisher->publishToChannels($article, ['telegram', 'facebook']);

// Publish via a single driver
$result = $publisher->driver('telegram')->publish($article);
```

<a name="publisher-manager-api"></a>
## PublisherManager API

| Method | Description |
|--------|-------------|
| `driver(?string $name)` | Resolve a driver by name (null = default) |
| `publishToChannels(Article $article, ?array $channels)` | Publish to multiple channels |
| `extend(string $driver, Closure $callback)` | Register a custom driver |
| `availableDrivers()` | List all available driver names |
| `getDefaultDriver()` | Get the default driver from config |

<a name="publish-logs"></a>
## Publish Logs

Every publish attempt is logged to the `publish_logs` table:

| Column | Description |
|--------|-------------|
| `article_id` | The published article |
| `channel` | Channel name (e.g., 'telegram') |
| `status` | 'success' or 'failure' |
| `external_id` | Platform-specific ID (tweet ID, post ID, etc.) |
| `external_url` | Public URL on the platform |
| `metadata` | JSON additional data |
| `error_message` | Error details on failure |

Query logs via the `Article` model:

```php
$article->publishLogs;  // All publish attempts
$article->wasPublishedTo('facebook');  // Check specific channel
```

<a name="duplicate-prevention"></a>
## Duplicate Prevention

The `PublisherManager` automatically skips channels where the article has already been successfully published:

```php
if ($article->wasPublishedTo($channel)) {
    Log::info("Already published to [{$channel}], skipping");
    continue;
}
```

This check uses the `publish_logs` table, looking for a log entry with `status = 'success'`.
