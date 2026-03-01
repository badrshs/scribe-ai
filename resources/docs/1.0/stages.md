# Pipeline Stages

---

- [Stage Contract](#stage-contract)
- [Built-in Stages](#built-in-stages)
- [Writing a Custom Stage](#writing-a-custom-stage)
- [Stage Skip Behaviour](#stage-skip-behaviour)

<a name="stage-contract"></a>
## Stage Contract

Every stage implements `Badr\ScribeAi\Contracts\Pipe`:

```php
namespace Badr\ScribeAi\Contracts;

use Badr\ScribeAi\Data\ContentPayload;
use Closure;

interface Pipe
{
    public function handle(ContentPayload $payload, Closure $next): mixed;
}
```

- Call `$next($payload)` to continue the pipeline
- Return `$payload->with([...])` **without** calling `$next()` to halt (reject)
- Use `$payload->with([...])` to create a new payload with updated fields — never mutate directly

<a name="built-in-stages"></a>
## Built-in Stages

### ScrapeStage

Uses `ContentSourceManager` to fetch raw content from the source URL. Auto-detects the right driver (web, rss, text) or honours `$payload->sourceDriver`.

**Skips when:** `rawContent` is already present, or no `sourceUrl` is set.

**Sets:** `rawContent`, `cleanedContent`, `title` (from source), `extra.source_meta`

**Event:** `ContentScraped` (driver, contentLength)

---

### AiRewriteStage

Sends scraped content to the AI for rewriting, categorisation, and enrichment. Returns structured JSON with title, content, metadata, category, tags, and an image prompt.

**Skips when:** No content to process.

**Rejects when:** AI returns `status: "reject"`.

**Sets:** `title`, `content`, `description`, `metaTitle`, `metaDescription`, `imagePrompt`, `categoryId`, `tags`, `slug`

**Event:** `ContentRewritten` (title, categoryId)

---

### GenerateImageStage

Creates a featured image using the configured AI image provider (DALL-E, Imagen, Flux, etc.).

**Skips when:** `imagePath` already set, or no `imagePrompt` provided.

**Sets:** `imagePath`

**Event:** `ImageGenerated` (imagePath)

---

### OptimizeImageStage

Resizes, compresses, and converts the image to WebP format.

**Skips when:** No `imagePath`, or `IMAGE_OPTIMIZE=false`.

**Sets:** `imagePath` (updated to optimised path)

**Event:** `ImageOptimized` (originalPath, optimizedPath)

---

### CreateArticleStage

Persists the processed content as an `Article` Eloquent model. Creates tags and attaches them. Marks `StagedContent` as published if applicable.

**Skips when:** Content was rejected.

**Sets:** `article`

**Event:** `ArticleCreated` (article)

---

### PublishStage

Publishes the article to all active channels via `PublisherManager`.

**Skips when:** No article was created.

**Sets:** `publishResults`

**Event:** `ArticlePublished` (per channel — result, channel name)

<a name="writing-a-custom-stage"></a>
## Writing a Custom Stage

```php
<?php

namespace App\Pipeline\Stages;

use Badr\ScribeAi\Contracts\Pipe;
use Badr\ScribeAi\Data\ContentPayload;
use Closure;
use Illuminate\Support\Facades\Log;

class TranslateStage implements Pipe
{
    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        if (! $payload->content) {
            Log::info('TranslateStage: no content, skipping');
            return $next($payload);
        }

        $translated = MyTranslator::translate($payload->content, 'es');

        Log::info('TranslateStage: translated to Spanish');

        return $next($payload->with(['content' => $translated]));
    }
}
```

**Register in config:**

```php
'pipeline' => [
    'stages' => [
        ScrapeStage::class,
        AiRewriteStage::class,
        TranslateStage::class,       // ← inserted
        GenerateImageStage::class,
        OptimizeImageStage::class,
        CreateArticleStage::class,
        PublishStage::class,
    ],
],
```

**Or at runtime:**

```php
$pipeline->through([
    ScrapeStage::class,
    AiRewriteStage::class,
    TranslateStage::class,
    CreateArticleStage::class,
])->process($payload);
```

<a name="stage-skip-behaviour"></a>
## Stage Skip Behaviour

Stages follow these conventions:

1. **Skip silently** when the required input is already present (e.g., ScrapeStage skips if `rawContent` is set)
2. **Skip with warning** when the required input is missing (e.g., AiRewriteStage skips if no content)
3. **Log every action** via `Log::info()` / `Log::warning()` with `source_url` context
4. **Report progress** via `ContentPipeline::reportProgress()` for real-time feedback
