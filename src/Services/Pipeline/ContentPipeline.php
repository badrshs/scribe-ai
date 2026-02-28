<?php

namespace Bader\ContentPublisher\Services\Pipeline;

use Bader\ContentPublisher\Data\ContentPayload;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates content processing through configurable stages.
 *
 * Uses Laravel's Pipeline to send a ContentPayload through an ordered
 * list of stages. Each stage transforms the payload and passes it along.
 *
 * Usage:
 *   $pipeline = app(ContentPipeline::class);
 *   $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));
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

    public function __construct(
        protected Pipeline $pipeline,
    ) {}

    /**
     * Register a callback invoked when each stage starts/finishes.
     *
     * Signature: function(string $stage, string $status): void
     * $status is 'started' or 'completed'.
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
     * Process a content payload through all pipeline stages.
     */
    public function process(ContentPayload $payload): ContentPayload
    {
        $this->reportProgress('Pipeline', 'started');

        Log::info('Content pipeline started', [
            'source_url' => $payload->sourceUrl,
            'staged_content_id' => $payload->stagedContent?->id,
        ]);

        $result = $this->pipeline
            ->send($payload)
            ->through($this->getStages())
            ->thenReturn();

        if ($result->rejected) {
            Log::info('Content rejected by pipeline', [
                'source_url' => $payload->sourceUrl,
                'reason' => $result->rejectionReason,
            ]);
        } else {
            Log::info('Content pipeline completed', [
                'source_url' => $payload->sourceUrl,
                'article_id' => $result->article?->id,
            ]);
        }

        $this->reportProgress('Pipeline', 'completed');
        $this->onProgress = null;

        return $result;
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
