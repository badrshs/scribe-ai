# Run Tracking & Resume

---

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Pipeline Run Model](#pipeline-run-model)
- [Resuming Failed Runs](#resuming-failed-runs)
- [Disabling Tracking](#disabling-tracking)
- [Querying Runs](#querying-runs)

<a name="overview"></a>
## Overview

Run tracking persists a `PipelineRun` record for every pipeline execution. After each stage completes, the payload is snapshotted to the database. If a stage fails, you can resume from exactly where it left off — no duplicated API calls, no re-scraping.

> {primary} Run tracking requires the `pipeline_runs` migration. Publish and run it:
> `php artisan vendor:publish --tag=scribe-ai-migrations && php artisan migrate`

<a name="how-it-works"></a>
## How It Works

1. **Pipeline starts** → a `PipelineRun` is created with status `pending` and the initial payload snapshot.
2. **Each stage runs** → the run is updated with `current_stage_index`, `current_stage_name`, and a fresh `payload_snapshot`.
3. **Success** → status becomes `completed`, `article_id` is linked.
4. **Failure** → status becomes `failed`, `error_stage` and `error_message` are recorded. The snapshot reflects the state *before* the failed stage so a resume replays it.
5. **Rejection** → status becomes `rejected`.

### Run Statuses

| Status | Description | Resumable? |
|--------|-------------|------------|
| `pending` | Just created, not yet started | No |
| `running` | Currently executing a stage | No |
| `completed` | All stages passed successfully | No |
| `failed` | A stage threw an exception | **Yes** |
| `rejected` | Content was rejected by a stage | No |

<a name="pipeline-run-model"></a>
## Pipeline Run Model

The `PipelineRun` model is a standard Eloquent model with these key columns:

| Column | Type | Description |
|--------|------|-------------|
| `source_url` | string | The URL being processed |
| `staged_content_id` | int nullable | Link to staged content |
| `article_id` | int nullable | Created article (on completion) |
| `status` | enum | Current run status |
| `stages` | json | Ordered list of stage class names |
| `current_stage_index` | int | Index of the current/last stage |
| `current_stage_name` | string | Short name of the current stage |
| `payload_snapshot` | json | Serialised `ContentPayload` state |
| `error_stage` | string nullable | Stage that caused the failure |
| `error_message` | string nullable | Error message |
| `started_at` | datetime | When the run started |
| `completed_at` | datetime | When the run finished |
| `failed_at` | datetime | When the run failed |

**Relationships:**

```php
$run->article;        // BelongsTo Article
$run->stagedContent;  // BelongsTo StagedContent
```

<a name="resuming-failed-runs"></a>
## Resuming Failed Runs

### Via Artisan

```bash
php artisan scribe:resume 42
```

This will:
1. Load run #42 and verify it's in `failed` status
2. Restore the payload from the last successful snapshot
3. Re-execute from the failed stage onwards

### Programmatically

```php
use Badr\ScribeAi\Services\Pipeline\ContentPipeline;
use Badr\ScribeAi\Models\PipelineRun;

$pipeline = app(ContentPipeline::class);

// By ID
$result = $pipeline->resume(42);

// By model
$run = PipelineRun::where('status', 'failed')->latest()->first();
$result = $pipeline->resume($run);
```

### Resume Output

The `scribe:resume` command displays rich progress output:

```
Resuming Pipeline Run #42
  URL:          https://example.com/article
  Failed at:    GenerateImage
  Error:        OpenAI image API error [429]: rate limited
  Resuming from: stage 2

  [3/6] GenerateImage  …
        ✓ completed — generated articles/abc123.png
  [4/6] OptimiseImage  …
        ✓ completed — optimised to articles/abc123.webp
  [5/6] CreateArticle  …
        ✓ completed — article #15
  [6/6] Publish  …
        ✓ completed — 2 channels
```

<a name="disabling-tracking"></a>
## Disabling Tracking

**Globally** via `.env`:

```dotenv
PIPELINE_TRACK_RUNS=false
```

**Per-instance** (useful in tests):

```php
$pipeline = app(ContentPipeline::class);
$result = $pipeline->withoutTracking()->process($payload);
```

> {warning} When tracking is disabled, `resume()` will throw a `RuntimeException`. The pipeline_runs table is not required when tracking is off.

<a name="querying-runs"></a>
## Querying Runs

```php
use Badr\ScribeAi\Models\PipelineRun;
use Badr\ScribeAi\Enums\PipelineRunStatus;

// All failed runs
$failed = PipelineRun::where('status', PipelineRunStatus::Failed)->get();

// Resumable runs
$resumable = PipelineRun::where('status', PipelineRunStatus::Failed)->get()
    ->filter(fn($run) => $run->isResumable());

// Recent completed runs
$recent = PipelineRun::where('status', PipelineRunStatus::Completed)
    ->latest('completed_at')
    ->limit(10)
    ->get();

// Run for a specific URL
$run = PipelineRun::where('source_url', 'https://example.com/article')->first();
```
