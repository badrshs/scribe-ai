<?php

namespace Bader\ContentPublisher\Jobs;

use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Models\StagedContent;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs a StagedContent or URL through the full content pipeline.
 *
 * Dispatched to the 'pipeline' queue for AI-heavy processing.
 */
class ProcessContentPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /** @var int[] */
    public array $backoff = [60, 300];

    public function __construct(
        protected ?int $stagedContentId = null,
        protected ?string $url = null,
    ) {
        $this->onQueue(config('scribe-ai.queue.pipeline', 'pipeline'));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $key = $this->stagedContentId
            ? "pipeline-{$this->stagedContentId}"
            : 'pipeline-url-' . md5($this->url ?? '');

        return [new WithoutOverlapping($key)];
    }

    public function handle(ContentPipeline $pipeline): void
    {
        $payload = $this->buildPayload();

        if (! $payload) {
            return;
        }

        $result = $pipeline->process($payload);

        if ($result->rejected) {
            Log::info('Pipeline job: content was rejected', [
                'staged_content_id' => $this->stagedContentId,
                'url' => $this->url,
                'reason' => $result->rejectionReason,
            ]);

            return;
        }

        Log::info('Pipeline job completed', [
            'staged_content_id' => $this->stagedContentId,
            'article_id' => $result->article?->id,
        ]);
    }

    protected function buildPayload(): ?ContentPayload
    {
        if ($this->stagedContentId) {
            $staged = StagedContent::query()->find($this->stagedContentId);

            if (! $staged) {
                Log::warning('Pipeline job: staged content not found', ['id' => $this->stagedContentId]);

                return null;
            }

            if ($staged->published) {
                Log::info('Pipeline job: staged content already published', ['id' => $this->stagedContentId]);

                return null;
            }

            return ContentPayload::fromStagedContent($staged);
        }

        if ($this->url) {
            return ContentPayload::fromUrl($this->url);
        }

        Log::warning('Pipeline job: no staged content ID or URL provided');

        return null;
    }
}
