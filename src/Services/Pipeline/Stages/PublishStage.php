<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
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
        if (! $payload->article) {
            Log::info('PublishStage: skipped (no article to publish)');

            return $next($payload);
        }

        $article = $payload->article->load(['category', 'tags']);

        try {
            $results = $this->publisher->publishToChannels($article);

            Log::info('PublishStage: publishing complete', [
                'article_id' => $article->id,
                'channels' => array_keys($results),
                'success_count' => collect($results)->filter(fn($r) => $r->success)->count(),
            ]);

            return $next($payload->with(['publishResults' => $results]));
        } catch (\Throwable $e) {
            Log::error('PublishStage: publishing failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);

            return $next($payload);
        }
    }
}
