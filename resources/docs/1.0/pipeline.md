# Content Pipeline

---

- [Overview](#overview)
- [Running the Pipeline](#running-the-pipeline)
- [Custom Stage Order](#custom-stage-order)
- [Progress Callbacks](#progress-callbacks)
- [Disabling Run Tracking](#disabling-tracking)
- [Error Handling](#error-handling)

<a name="overview"></a>
## Overview

`ContentPipeline` is the central orchestrator. It takes a `ContentPayload` DTO and sends it through an ordered list of stages. Each stage processes the payload and passes it to the next.

The default stage order (configured in `config/scribe-ai.php`):

```
ScrapeStage → AiRewriteStage → GenerateImageStage → OptimizeImageStage → CreateArticleStage → PublishStage
```

<a name="running-the-pipeline"></a>
## Running the Pipeline

**Via Artisan:**

```bash
# Queued (default)
php artisan scribe:process-url https://example.com/article

# Synchronous with live progress
php artisan scribe:process-url https://example.com/article --sync
```

**Programmatic:**

```php
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;

$pipeline = app(ContentPipeline::class);

$result = $pipeline->process(
    ContentPayload::fromUrl('https://example.com/article')
);

// Check the result
if ($result->rejected) {
    echo "Rejected: {$result->rejectionReason}";
} else {
    echo "Article created: #{$result->article->id}";
}
```

**Via Job (queued):**

```php
use Bader\ContentPublisher\Jobs\ProcessContentPipelineJob;

ProcessContentPipelineJob::dispatch(url: 'https://example.com/article');
```

<a name="custom-stage-order"></a>
## Custom Stage Order

Override stages for a single `process()` call:

```php
use Bader\ContentPublisher\Services\Pipeline\Stages\*;

$pipeline->through([
    ScrapeStage::class,
    MyCustomStage::class,    // your own stage
    CreateArticleStage::class,
])->process($payload);
```

Custom stages are consumed and cleared after a single `process()` call.

Or change the default order permanently in `config/scribe-ai.php`:

```php
'pipeline' => [
    'stages' => [
        ScrapeStage::class,
        AiRewriteStage::class,
        // GenerateImageStage::class,  // removed
        CreateArticleStage::class,
        PublishStage::class,
    ],
],
```

<a name="progress-callbacks"></a>
## Progress Callbacks

Register a callback to receive real-time stage updates:

```php
$pipeline->onProgress(function (string $stage, string $status) {
    echo "{$stage}: {$status}\n";
})->process($payload);

// Output:
// Pipeline: started
// Scrape: started
// Scrape: completed — 4523 chars via web driver
// AI Rewrite: started
// AI Rewrite: completed — "Article Title"
// ...
// Pipeline: completed
```

> {info} The progress callback is cleared after each `process()` call.

<a name="disabling-tracking"></a>
## Disabling Run Tracking

Disable tracking for a single call:

```php
$pipeline->withoutTracking()->process($payload);
```

Or globally via config:

```env
PIPELINE_TRACK_RUNS=false
```

<a name="error-handling"></a>
## Error Handling

When `PIPELINE_HALT_ON_ERROR=true` (default):
- Stage exceptions halt the pipeline
- The payload is marked as rejected with the error message
- A `PipelineFailed` event is dispatched
- The run can be resumed later with `scribe:resume`

When `PIPELINE_HALT_ON_ERROR=false`:
- Stage exceptions are logged as warnings
- The pipeline continues to the next stage
- The failing stage is simply skipped
