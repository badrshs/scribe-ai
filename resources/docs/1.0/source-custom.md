# Custom Content Source Drivers

---

- [Overview](#overview)
- [The ContentSource Contract](#contract)
- [Implementing a Driver](#implementing)
- [Registering the Driver](#registering)
- [Auto-Detection Order](#detection-order)

<a name="overview"></a>
## Overview

Add support for any content source by implementing the `ContentSource` contract and registering it with the `ContentSourceManager`. Custom drivers integrate seamlessly with auto-detection and the pipeline.

<a name="contract"></a>
## The ContentSource Contract

```php
namespace Bader\ContentPublisher\Contracts;

interface ContentSource
{
    /**
     * Fetch content from the given identifier.
     *
     * @return array{content: string, title?: string|null, meta?: array<string, mixed>}
     */
    public function fetch(string $identifier): array;

    /**
     * Whether this driver can handle the given identifier.
     */
    public function supports(string $identifier): bool;

    /**
     * Unique driver name.
     */
    public function name(): string;
}
```

<a name="implementing"></a>
## Implementing a Driver

**Example: YouTube transcript driver**

```php
<?php

namespace App\Sources;

use Bader\ContentPublisher\Contracts\ContentSource;
use Illuminate\Support\Facades\Http;

class YouTubeDriver implements ContentSource
{
    public function __construct(protected array $config = []) {}

    public function fetch(string $identifier): array
    {
        // Extract video ID from URL
        preg_match('/[?&]v=([^&]+)/', $identifier, $matches);
        $videoId = $matches[1] ?? throw new \RuntimeException("Invalid YouTube URL");

        // Fetch transcript via your preferred API
        $transcript = $this->fetchTranscript($videoId);

        return [
            'content' => $transcript['text'],
            'title'   => $transcript['title'],
            'meta'    => [
                'source_driver' => 'youtube',
                'video_id'      => $videoId,
                'url'           => $identifier,
                'duration'      => $transcript['duration'] ?? null,
            ],
        ];
    }

    public function supports(string $identifier): bool
    {
        return (bool) preg_match(
            '#(youtube\.com/watch|youtu\.be/)#i',
            $identifier
        );
    }

    public function name(): string
    {
        return 'youtube';
    }

    private function fetchTranscript(string $videoId): array
    {
        // Your transcript fetching implementation
    }
}
```

<a name="registering"></a>
## Registering the Driver

In your application's service provider:

```php
use Bader\ContentPublisher\Services\Sources\ContentSourceManager;

public function register(): void
{
    app(ContentSourceManager::class)->extend('youtube', function (array $config) {
        return new \App\Sources\YouTubeDriver($config);
    });
}
```

Add driver config to `config/scribe-ai.php`:

```php
'sources' => [
    'drivers' => [
        'youtube' => [
            'api_key' => env('YOUTUBE_API_KEY'),
        ],
        // ... other drivers
    ],
],
```

**Usage:**

```bash
# Auto-detected (supports() returns true for YouTube URLs)
php artisan scribe:process-url "https://youtube.com/watch?v=dQw4w9WgXcQ" --sync

# Force the driver
php artisan scribe:process-url "https://youtube.com/watch?v=dQw4w9WgXcQ" --source=youtube
```

<a name="detection-order"></a>
## Auto-Detection Order

Custom drivers are inserted between RSS and Web in the detection order:

```
rss → [custom drivers] → web → text
```

This means your driver's `supports()` method is checked before the generic web driver, but after RSS. If you need different priority, force the driver explicitly via `--source=` or the payload's `sourceDriver` property.
