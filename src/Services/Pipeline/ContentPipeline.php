<?php

namespace Badr\ScribeAi\Services\Pipeline;

use Badr\ScribeAi\Data\ContentPayload;
use Badr\ScribeAi\Enums\PipelineRunStatus;
use Badr\ScribeAi\Events\PipelineCompleted;
use Badr\ScribeAi\Events\PipelineFailed;
use Badr\ScribeAi\Events\PipelineStarted;
use Badr\ScribeAi\Models\PipelineRun;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates content processing through configurable stages.
 *
 * Each run is persisted in the `pipeline_runs` table with a payload
 * snapshot after every successful stage, enabling resume on failure.
 *
 * Usage:
 *   $pipeline = app(ContentPipeline::class);
 *   $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));
 *
 * Resume a failed run:
 *   $result = $pipeline->resume($pipelineRunId);
 *
 * Custom stages:
 *   $result = $pipeline->through([ScrapeStage::class, MyCustomStage::class])->process($payload);
 */
class ContentPipeline
{
    /** @var class-string[]|null */
    protected ?array $customStages = null;

    /** @var (\Closure(string, string): void)|null */
    protected ?\Closure $onProgress = null;

    /** Whether to persist run tracking (can be disabled e.g. in tests). */
    protected bool $tracking = true;

    /** Whether run tracking has been explicitly disabled via withoutTracking(). */
    protected bool $trackingOverride = false;

    public function __construct(
        protected Pipeline $pipeline,
    ) {}

    /**
     * Register a callback invoked when each stage starts/finishes.
     *
     * Signature: function(string $stage, string $status): void
     */
    public function onProgress(\Closure $callback): static
    {
        $this->onProgress = $callback;

        return $this;
    }

    /**
     * Report progress for the current stage (called by stages).
     */
    public function reportProgress(string $stage, string $status): void
    {
        if ($this->onProgress) {
            ($this->onProgress)($stage, $status);
        }
    }

    /**
     * Disable run tracking for this instance (useful in tests or one-off scripts).
     */
    public function withoutTracking(): static
    {
        $this->tracking = false;
        $this->trackingOverride = true;

        return $this;
    }

    /**
     * Process a content payload through all pipeline stages.
     */
    public function process(ContentPayload $payload): ContentPayload
    {
        $stages = $this->getStages();

        $run = $this->createRun($payload, $stages);

        return $this->executeStages($payload, $stages, 0, $run);
    }

    /**
     * Resume a previously failed pipeline run.
     *
     * Picks up from the stage that failed, using the last successful
     * payload snapshot.
     */
    public function resume(int|PipelineRun $run): ContentPayload
    {
        if (! $this->shouldTrack()) {
            throw new \RuntimeException(
                'Pipeline run tracking is disabled. Enable it via PIPELINE_TRACK_RUNS=true to use resume.'
            );
        }

        $run = $run instanceof PipelineRun ? $run : PipelineRun::query()->findOrFail($run);

        if (! $run->isResumable()) {
            throw new \RuntimeException(
                "Pipeline run #{$run->id} is not resumable (status: {$run->status->value})"
            );
        }

        $stages = $run->stages ?? $this->getStages();
        $startIndex = $run->current_stage_index;
        $payload = ContentPayload::fromSnapshot($run->payload_snapshot ?? []);

        Log::info('Resuming pipeline run', [
            'run_id' => $run->id,
            'from_stage' => $stages[$startIndex] ?? 'unknown',
            'stage_index' => $startIndex,
        ]);

        $this->reportProgress('Pipeline', 'resuming from ' . PipelineRun::stageShortName($stages[$startIndex] ?? ''));

        return $this->executeStages($payload, $stages, $startIndex, $run);
    }

    /**
     * Override the default stages for the next process() call.
     *
     * @param  class-string[]  $stages
     */
    public function through(array $stages): static
    {
        $this->customStages = $stages;

        return $this;
    }

    // ──────────────────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────────────────

    /**
     * Run stages sequentially from $startIndex, tracking each one.
     */
    protected function executeStages(ContentPayload $payload, array $stages, int $startIndex, ?PipelineRun $run): ContentPayload
    {
        $this->reportProgress('Pipeline', 'started');

        event(new PipelineStarted($payload, $run?->id));

        Log::info('Content pipeline started', [
            'run_id' => $run?->id,
            'source_url' => $payload->sourceUrl,
            'staged_content_id' => $payload->stagedContent?->id,
            'from_stage' => $startIndex,
        ]);

        $current = $payload;

        for ($i = $startIndex; $i < count($stages); $i++) {
            $stageClass = $stages[$i];
            $stageName = PipelineRun::stageShortName($stageClass);

            $run?->markRunning($i, $stageName);

            try {
                $stage = app($stageClass);
                $current = $stage->handle($current, fn(ContentPayload $p) => $p);
            } catch (\Throwable $e) {
                Log::warning("Pipeline stage [{$stageName}] threw an exception", [
                    'run_id' => $run?->id,
                    'error' => $e->getMessage(),
                ]);

                $run?->markFailed($stageName, $e->getMessage());

                event(new PipelineFailed($current, $e->getMessage(), $stageName, $run?->id));

                // Snapshot at the state *before* the failed stage so resume replays it
                $run?->markStageCompleted($i, $current->toSnapshot());

                $this->reportProgress($stageName, 'failed — ' . $e->getMessage());

                if (config('scribe-ai.pipeline.halt_on_error', true)) {
                    $current = $current->with([
                        'rejected' => true,
                        'rejectionReason' => "{$stageName} failed: " . $e->getMessage(),
                    ]);
                    break;
                }

                continue;
            }

            // Snapshot after successful completion
            $run?->markStageCompleted($i + 1, $current->toSnapshot());

            // Check if the stage rejected the payload
            if ($current->rejected) {
                Log::info('Content rejected by pipeline', [
                    'run_id' => $run?->id,
                    'source_url' => $payload->sourceUrl,
                    'reason' => $current->rejectionReason,
                    'at_stage' => $stageName,
                ]);

                $run?->markRejected($current->rejectionReason ?? 'unknown');
                $this->reportProgress('Pipeline', 'completed');

                event(new PipelineFailed($current, $current->rejectionReason ?? 'rejected', $stageName, $run?->id));

                $this->cleanup();

                return $current;
            }
        }

        // Final outcome
        if ($current->rejected) {
            $isFailed = $run && $run->status === PipelineRunStatus::Failed;

            if ($run && ! $isFailed && $run->status !== PipelineRunStatus::Rejected) {
                $run->markRejected($current->rejectionReason ?? 'unknown');
            }

            Log::info('Content rejected by pipeline', [
                'run_id' => $run?->id,
                'source_url' => $payload->sourceUrl,
                'reason' => $current->rejectionReason,
            ]);

            event(new PipelineFailed($current, $current->rejectionReason ?? 'rejected', null, $run?->id));
        } else {
            $run?->markCompleted($current->article?->id);
            Log::info('Content pipeline completed', [
                'run_id' => $run?->id,
                'source_url' => $payload->sourceUrl,
                'article_id' => $current->article?->id,
            ]);

            event(new PipelineCompleted($current, $run?->id));
        }

        $this->reportProgress('Pipeline', 'completed');
        $this->cleanup();

        return $current;
    }

    /**
     * Determine if run tracking is active.
     */
    protected function shouldTrack(): bool
    {
        if ($this->trackingOverride) {
            return $this->tracking;
        }

        return (bool) config('scribe-ai.pipeline.track_runs', true);
    }

    /**
     * Create a PipelineRun record (if tracking enabled and table exists).
     */
    protected function createRun(ContentPayload $payload, array $stages): ?PipelineRun
    {
        if (! $this->shouldTrack()) {
            return null;
        }

        if (! $this->pipelineRunsTableExists()) {
            throw new \RuntimeException(
                'Pipeline run tracking is enabled but the `pipeline_runs` table does not exist. '
                    . 'Run `php artisan vendor:publish --tag=scribe-ai-migrations && php artisan migrate`, '
                    . 'or set PIPELINE_TRACK_RUNS=false to disable tracking.'
            );
        }

        return PipelineRun::query()->create([
            'source_url' => $payload->sourceUrl,
            'staged_content_id' => $payload->stagedContent?->id,
            'status' => PipelineRunStatus::Pending,
            'stages' => $stages,
            'payload_snapshot' => $payload->toSnapshot(),
            'current_stage_index' => 0,
        ]);
    }

    /**
     * Check if the pipeline_runs table has been migrated.
     */
    protected function pipelineRunsTableExists(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('pipeline_runs');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function cleanup(): void
    {
        $this->onProgress = null;
    }

    /**
     * Get the ordered list of pipeline stages.
     *
     * @return class-string[]
     */
    protected function getStages(): array
    {
        if ($this->customStages !== null) {
            $stages = $this->customStages;
            $this->customStages = null;

            return $stages;
        }

        return config('scribe-ai.pipeline.stages', []);
    }
}
