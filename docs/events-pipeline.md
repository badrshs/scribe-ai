# Pipeline Events

---

- [PipelineStarted](#pipeline-started)
- [PipelineCompleted](#pipeline-completed)
- [PipelineFailed](#pipeline-failed)
- [Dispatch Timing](#dispatch-timing)

<a name="pipeline-started"></a>
## PipelineStarted

Fired when the pipeline begins executing stages (including on resume).

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | The initial payload |
| `runId` | `?int` | Pipeline run ID (null if tracking disabled) |

```php
use Bader\ContentPublisher\Events\PipelineStarted;

Event::listen(PipelineStarted::class, function (PipelineStarted $event) {
    Log::info("Pipeline started for: {$event->payload->sourceUrl}");
});
```

<a name="pipeline-completed"></a>
## PipelineCompleted

Fired when all stages complete successfully and the payload is not rejected.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Final payload with all results |
| `runId` | `?int` | Pipeline run ID |

```php
use Bader\ContentPublisher\Events\PipelineCompleted;

Event::listen(PipelineCompleted::class, function (PipelineCompleted $event) {
    $article = $event->payload->article;
    Log::info("Pipeline completed: article #{$article?->id} — {$article?->title}");
});
```

<a name="pipeline-failed"></a>
## PipelineFailed

Fired when the pipeline halts due to an exception or content rejection.

**Properties:**

| Property | Type | Description |
|----------|------|-------------|
| `payload` | `ContentPayload` | Payload at the point of failure |
| `reason` | `string` | Error message or rejection reason |
| `stage` | `?string` | Stage name where failure occurred (null for post-loop rejections) |
| `runId` | `?int` | Pipeline run ID |

```php
use Bader\ContentPublisher\Events\PipelineFailed;

Event::listen(PipelineFailed::class, function (PipelineFailed $event) {
    Log::error("Pipeline failed at stage [{$event->stage}]: {$event->reason}");
});
```

**This event fires in three scenarios:**

1. **Exception** — a stage throws an exception and `halt_on_error` is true
2. **Rejection in loop** — a stage sets `rejected: true` on the payload (mid-pipeline)
3. **Rejection post-loop** — payload is rejected after all stages complete

<a name="dispatch-timing"></a>
## Dispatch Timing

```
process() called
  └── PipelineStarted dispatched
       │
       ├── Stage 1 runs...
       ├── Stage 2 runs...
       ├── ...
       │
       ├── On exception → PipelineFailed dispatched
       ├── On rejection → PipelineFailed dispatched
       │
       └── All stages pass → PipelineCompleted dispatched
```

Events are dispatched **synchronously** within the pipeline execution. Use queued listeners if you need decoupled processing.
