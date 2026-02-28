<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Bader\ContentPublisher\Services\Publishing\PublisherManager;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline stage: Publish the created article to all active channels.
 *
 * This is the final stage. It delegates to the PublisherManager
 * which dispatches to all configured publisher drivers.
 *
 * Skipped if no article was created (e.g., content was rejected).
 */
class PublishStage implements Pipe
{
    public function __construct(
        protected PublisherManager $publisher,
    ) {}

    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        $pipeline = app(ContentPipeline::class);
        $pipeline->reportProgress('Publish', 'started');

        if (! $payload->article) {
            Log::info('PublishStage: skipped (no article to publish)');
            $pipeline->reportProgress('Publish', 'skipped — no article to publish');

            return $next($payload);
        }

        $article = $payload->article->load(['category', 'tags']);

        try {
            $results = $this->publisher->publishToChannels($article);

            $successCount = collect($results)->filter(fn($r) => $r->success)->count();

            Log::info('PublishStage: publishing complete', [
                'article_id' => $article->id,
                'channels' => array_keys($results),
                'success_count' => $successCount,
            ]);

            $pipeline->reportProgress('Publish', 'completed — ' . $successCount . '/' . count($results) . ' channels succeeded');

            return $next($payload->with(['publishResults' => $results]));
        } catch (\Throwable $e) {
            Log::error('PublishStage: publishing failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);

            $pipeline->reportProgress('Publish', 'failed — ' . $e->getMessage());

            if (config('scribe-ai.pipeline.halt_on_error', true)) {
                return $payload->with([
                    'rejected' => true,
                    'rejectionReason' => 'Publishing failed: ' . $e->getMessage(),
                ]);
            }

            return $next($payload);
        }
    }
}
