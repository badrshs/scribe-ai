# Image Optimization

---

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Configuration](#configuration)
- [Optimize Uploaded Files](#optimize-uploaded)
- [Optimize Existing Files](#optimize-existing)
- [Pipeline Integration](#pipeline-integration)

<a name="overview"></a>
## Overview

The `ImageOptimizer` service resizes large images and converts them to WebP format for faster page loads. It is used automatically by the `OptimizeImageStage` in the content pipeline, and can also be called directly.

<a name="how-it-works"></a>
## How It Works

1. **Resize** — Images wider than `max_width` (default 1600 px) are proportionally scaled down.
2. **WebP conversion** — Files larger than `min_size_for_conversion` (default 20 KB) are re-encoded as WebP at the configured quality.
3. **Storage** — The optimized image is stored on the configured disk under the `images/` directory with a unique filename.

The service uses PHP's built-in GD extension — no external binaries required.

> {info} If the GD extension is not loaded, the service will skip optimization and store the original file unchanged, logging a warning.

<a name="configuration"></a>
## Configuration

All settings live under `scribe-ai.images`:

```php
// config/scribe-ai.php

'images' => [
    'max_width'             => env('IMAGE_MAX_WIDTH', 1600),
    'quality'               => env('IMAGE_QUALITY', 82),
    'min_size_for_conversion' => env('IMAGE_MIN_SIZE', 20480), // bytes
    'disk'                  => env('IMAGE_DISK', 'public'),
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `max_width` | `1600` | Maximum width in pixels. Taller images are scaled proportionally. |
| `quality` | `82` | WebP quality (0–100). Lower = smaller file, lower fidelity. |
| `min_size_for_conversion` | `20480` | Files below this size (bytes) are stored as-is without WebP conversion. |
| `disk` | `public` | Laravel filesystem disk used for storage. |

<a name="optimize-uploaded"></a>
## Optimize Uploaded Files

```php
use Bader\ContentPublisher\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;

$optimizer = app(ImageOptimizer::class);

// Returns the storage path (e.g. "images/abc123.webp")
$path = $optimizer->optimizeAndStore($uploadedFile);
```

<a name="optimize-existing"></a>
## Optimize Existing Files

For images already on disk:

```php
// Reads from the configured disk, optimizes, and writes back as WebP
$newPath = $optimizer->optimizeExisting('images/original.png');
```

<a name="pipeline-integration"></a>
## Pipeline Integration

The `OptimizeImageStage` runs automatically after `GenerateImageStage`:

```
ScrapeStage → AiRewriteStage → GenerateImageStage → OptimizeImageStage → …
```

It looks for `imagePath` on the payload:

- **Present** — optimizes the image and replaces `imagePath` with the new path.
- **Missing** — skips silently and passes the payload to the next stage.

To disable optimization while keeping image generation, remove `OptimizeImageStage` from your pipeline stages:

```php
// config/scribe-ai.php
'pipeline' => [
    'stages' => [
        \Bader\ContentPublisher\Services\Pipeline\Stages\ScrapeStage::class,
        \Bader\ContentPublisher\Services\Pipeline\Stages\AiRewriteStage::class,
        \Bader\ContentPublisher\Services\Pipeline\Stages\GenerateImageStage::class,
        // OptimizeImageStage removed
        \Bader\ContentPublisher\Services\Pipeline\Stages\CreateArticleStage::class,
        \Bader\ContentPublisher\Services\Pipeline\Stages\PublishStage::class,
    ],
],
```
