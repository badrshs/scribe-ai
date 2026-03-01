# Event System

---

- [Overview](#overview)
- [Available Events](#available-events)
- [Listening to Events](#listening-to-events)
- [Event Properties](#event-properties)
- [Practical Examples](#practical-examples)

<a name="overview"></a>
## Overview

Scribe AI dispatches Laravel events at every significant point in the pipeline. These events allow you to hook into the workflow — log analytics, send notifications, trigger integrations, or modify behaviour without touching the core pipeline code.

All events use the `Dispatchable` and `SerializesModels` traits, making them compatible with queued listeners.

<a name="available-events"></a>
## Available Events

### Pipeline Events

| Event | Fired When |
|-------|-----------|
| [`PipelineStarted`](/docs/1.0/events-pipeline) | Pipeline begins processing |
| [`PipelineCompleted`](/docs/1.0/events-pipeline) | Pipeline finishes successfully |
| [`PipelineFailed`](/docs/1.0/events-pipeline) | Pipeline halts due to error or rejection |

### Stage Events

| Event | Fired When |
|-------|-----------|
| [`ContentScraped`](/docs/1.0/events-stages) | ScrapeStage fetches content |
| [`ContentRewritten`](/docs/1.0/events-stages) | AiRewriteStage rewrites content |
| [`ImageGenerated`](/docs/1.0/events-stages) | GenerateImageStage creates an image |
| [`ImageOptimized`](/docs/1.0/events-stages) | OptimizeImageStage processes the image |
| [`ArticleCreated`](/docs/1.0/events-stages) | CreateArticleStage persists the article |
| [`ArticlePublished`](/docs/1.0/events-stages) | PublishStage publishes to a channel |

<a name="listening-to-events"></a>
## Listening to Events

### In EventServiceProvider

```php
use Bader\ContentPublisher\Events\ArticleCreated;
use Bader\ContentPublisher\Events\PipelineCompleted;

protected $listen = [
    ArticleCreated::class => [
        \App\Listeners\NotifyEditors::class,
    ],
    PipelineCompleted::class => [
        \App\Listeners\UpdateDashboard::class,
    ],
];
```

### Closure Listeners

```php
use Illuminate\Support\Facades\Event;
use Bader\ContentPublisher\Events\ArticlePublished;

Event::listen(ArticlePublished::class, function (ArticlePublished $event) {
    Log::info("Published to {$event->channel}", [
        'title' => $event->payload->title,
        'success' => $event->result->success,
    ]);
});
```

### Queued Listeners

```php
use Bader\ContentPublisher\Events\PipelineCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCompletionNotification implements ShouldQueue
{
    public function handle(PipelineCompleted $event): void
    {
        // Runs asynchronously after the pipeline finishes
        Notification::send($admins, new PipelineCompletedNotification($event->payload));
    }
}
```

<a name="event-properties"></a>
## Event Properties

All events carry the `ContentPayload`:

```php
$event->payload->title;
$event->payload->sourceUrl;
$event->payload->article;
```

Additional properties vary by event — see [Pipeline Events](/docs/1.0/events-pipeline) and [Stage Events](/docs/1.0/events-stages) for details.

<a name="practical-examples"></a>
## Practical Examples

**Track pipeline analytics:**

```php
Event::listen(PipelineCompleted::class, function ($event) {
    PipelineMetrics::create([
        'url' => $event->payload->sourceUrl,
        'article_id' => $event->payload->article?->id,
        'run_id' => $event->runId,
        'completed_at' => now(),
    ]);
});
```

**Notify on failure:**

```php
Event::listen(PipelineFailed::class, function ($event) {
    Notification::route('slack', config('services.slack.webhook'))
        ->notify(new PipelineFailedNotification(
            url: $event->payload->sourceUrl,
            reason: $event->reason,
            stage: $event->stage,
        ));
});
```

**Auto-tweet after publish:**

```php
Event::listen(ArticlePublished::class, function ($event) {
    if ($event->channel === 'wordpress' && $event->result->success) {
        dispatch(new TweetArticle($event->result->externalUrl));
    }
});
```
