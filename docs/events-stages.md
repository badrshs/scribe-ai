# Stage Events

---

- [ContentScraped](#content-scraped)
- [ContentRewritten](#content-rewritten)
- [ImageGenerated](#image-generated)
- [ImageOptimized](#image-optimized)
- [ArticleCreated](#article-created)
- [ArticlePublished](#article-published)
- [Skipped Stages](#skipped-stages)

<a name="content-scraped"></a>
## ContentScraped

Fired after the `ScrapeStage` successfully fetches content from any source driver.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload after scraping |
| `driver` | `string` | Source driver name used (web, rss, text) |
| `contentLength` | `int` | Length of the fetched content in bytes |

```php
Event::listen(ContentScraped::class, function ($event) {
    Log::info("Scraped {$event->contentLength} bytes via {$event->driver}");
});
```

> {info} Not fired if the stage skips (rawContent already set on payload).

<a name="content-rewritten"></a>
## ContentRewritten

Fired after the `AiRewriteStage` produces AI-rewritten content.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload after rewriting |
| `title` | `string` | The generated article title |
| `categoryId` | `?int` | Selected category ID |

```php
Event::listen(ContentRewritten::class, function ($event) {
    Log::info("AI rewrote: '{$event->title}' in category #{$event->categoryId}");
});
```

<a name="image-generated"></a>
## ImageGenerated

Fired after the `GenerateImageStage` creates an image.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload after image generation |
| `imagePath` | `string` | Storage path of the generated image |

```php
Event::listen(ImageGenerated::class, function ($event) {
    Log::info("Image generated at: {$event->imagePath}");
});
```

> {info} Not fired if the stage skips (no `imagePrompt` on the payload).

<a name="image-optimized"></a>
## ImageOptimized

Fired after the `OptimizeImageStage` processes the image (resize + WebP conversion).

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload after optimisation |
| `originalPath` | `string` | Path before optimisation |
| `optimizedPath` | `string` | Path after optimisation (usually .webp) |

```php
Event::listen(ImageOptimized::class, function ($event) {
    Log::info("Image optimized: {$event->originalPath} → {$event->optimizedPath}");
});
```

<a name="article-created"></a>
## ArticleCreated

Fired after the `CreateArticleStage` persists a new article to the database.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload with the created article |
| `article` | `Article` | The Eloquent Article model |

```php
Event::listen(ArticleCreated::class, function ($event) {
    // Notify editors
    Notification::send(
        User::editors()->get(),
        new NewArticleNotification($event->article)
    );
});
```

<a name="article-published"></a>
## ArticlePublished

Fired **per channel** after each publish attempt in the `PublishStage`.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload at publication time |
| `result` | `PublishResult` | Success/failure details |
| `channel` | `string` | Channel name (e.g., 'telegram') |

```php
Event::listen(ArticlePublished::class, function ($event) {
    if ($event->result->success) {
        Log::info("Published to {$event->channel}: {$event->result->externalUrl}");
    } else {
        Log::warning("Publish failed on {$event->channel}: {$event->result->error}");
    }
});
```

> {primary} If publishing to 3 channels, this event fires 3 times — once per channel.

<a name="skipped-stages"></a>
## Skipped Stages

Stage events are **not** fired when a stage skips. Stages skip when:

- `ScrapeStage` — `rawContent` is already set on the payload
- `GenerateImageStage` — `imagePrompt` is empty/null
- `OptimizeImageStage` — `imagePath` is empty/null or optimisation is disabled
- `PublishStage` — no article is set on the payload

Design your listeners to handle the absence of these events gracefully.
