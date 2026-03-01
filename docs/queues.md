# Queues & Jobs

---

- [Overview](#overview)
- [Queue Configuration](#queue-config)
- [ProcessContentPipelineJob](#pipeline-job)
- [PublishArticleJob](#publish-job)
- [Running Workers](#running-workers)
- [Synchronous Mode](#sync-mode)
- [Monitoring](#monitoring)

<a name="overview"></a>
## Overview

Scribe AI ships two queueable jobs that run the content pipeline and publish articles asynchronously. By default, every `scribe:process-url` call dispatches the pipeline job onto a queue so your web process stays responsive.

<a name="queue-config"></a>
## Queue Configuration

```php
// config/scribe-ai.php

'queue' => [
    'pipeline'   => env('SCRIBE_PIPELINE_QUEUE', 'default'),
    'publishing' => env('SCRIBE_PUBLISHING_QUEUE', 'default'),
],
```

| Key | Default | Description |
|-----|---------|-------------|
| `pipeline` | `default` | Queue name for pipeline processing jobs |
| `publishing` | `default` | Queue name for article publishing jobs |

> {info} Using separate queue names lets you assign dedicated workers with different concurrency, memory, and timeout settings.

<a name="pipeline-job"></a>
## ProcessContentPipelineJob

Runs the full content pipeline for a single URL.

| Property | Value |
|----------|-------|
| **Queue** | `scribe-ai.queue.pipeline` |
| **Tries** | `2` |
| **Timeout** | `300` seconds |
| **Backoff** | `[60, 300]` seconds (exponential) |
| **Middleware** | `WithoutOverlapping` (keyed by URL) |

```php
use Bader\ContentPublisher\Jobs\ProcessContentPipelineJob;

ProcessContentPipelineJob::dispatch($url);

// With extra payload fields
ProcessContentPipelineJob::dispatch($url, [
    'categories' => [1 => 'Tech', 2 => 'Science'],
    'source_type' => 'rss',
]);
```

The `WithoutOverlapping` middleware ensures only one job processes a given URL at a time, preventing duplicate articles.

<a name="publish-job"></a>
## PublishArticleJob

Publishes a saved article to one or more channels.

| Property | Value |
|----------|-------|
| **Queue** | `scribe-ai.queue.publishing` |
| **Tries** | `3` |
| **Backoff** | `[60, 300]` seconds |

```php
use Bader\ContentPublisher\Jobs\PublishArticleJob;

// Publish to all configured channels
PublishArticleJob::dispatch($articleId);

// Publish to specific channels
PublishArticleJob::dispatch($articleId, ['telegram', 'facebook']);
```

<a name="running-workers"></a>
## Running Workers

Start queue workers for both queues:

```bash
# Single worker for both
php artisan queue:work

# Dedicated workers with custom settings
php artisan queue:work --queue=pipeline --timeout=600 --memory=512
php artisan queue:work --queue=publishing --timeout=120
```

For production, use a process manager like Supervisor:

```ini
[program:scribe-pipeline]
command=php /path/to/artisan queue:work --queue=pipeline --timeout=600 --sleep=3 --tries=2
numprocs=2
autostart=true
autorestart=true

[program:scribe-publishing]
command=php /path/to/artisan queue:work --queue=publishing --timeout=120 --sleep=3 --tries=3
numprocs=1
autostart=true
autorestart=true
```

<a name="sync-mode"></a>
## Synchronous Mode

For development or debugging, bypass the queue entirely:

```bash
# CLI flag
php artisan scribe:process-url https://example.com --sync
```

```php
// Programmatic
app(ContentPipeline::class)->process($payload);
```

When using `--sync`, the job runs in the current process and output is printed directly.

<a name="monitoring"></a>
## Monitoring

### Laravel Horizon

If you use [Laravel Horizon](https://laravel.com/docs/horizon), add the Scribe queues to your Horizon config:

```php
// config/horizon.php
'environments' => [
    'production' => [
        'scribe-pipeline' => [
            'connection' => 'redis',
            'queue'      => ['pipeline'],
            'balance'    => 'auto',
            'processes'  => 2,
            'tries'      => 2,
            'timeout'    => 600,
        ],
        'scribe-publishing' => [
            'connection' => 'redis',
            'queue'      => ['publishing'],
            'balance'    => 'auto',
            'processes'  => 1,
            'tries'      => 3,
            'timeout'    => 120,
        ],
    ],
],
```

### Failed Jobs

Both jobs use Laravel's built-in failed job handling. Check failures with:

```bash
php artisan queue:failed
php artisan queue:retry {id}
```

Pipeline runs also track failure state — use `scribe:resume {runId}` to retry a failed pipeline from the last completed stage.
