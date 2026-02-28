<?php

namespace Bader\ContentPublisher\Jobs;

use Bader\ContentPublisher\Models\Article;
use Bader\ContentPublisher\Services\Publishing\PublisherManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Publishes an existing article to all active channels.
 *
 * Useful for re-publishing or publishing articles that were created
 * outside the pipeline (e.g., manually through an admin panel).
 */
class PublishArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [60, 300];

    public function __construct(
        protected int $articleId,
        protected ?array $channels = null,
    ) {
        $this->onQueue(config('scribe-ai.queue.publishing', 'publishing'));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping("publish-article-{$this->articleId}")];
    }

    public function handle(PublisherManager $publisher): void
    {
        $article = Article::query()
            ->with(['category', 'tags'])
            ->find($this->articleId);

        if (! $article) {
            Log::warning('PublishArticleJob: article not found', ['id' => $this->articleId]);

            return;
        }

        if (! $article->isPublished()) {
            Log::info('PublishArticleJob: article not yet published, releasing', ['id' => $this->articleId]);
            $this->release(60);

            return;
        }

        $results = $publisher->publishToChannels($article, $this->channels);

        $successCount = collect($results)->filter(fn($r) => $r->success)->count();
        $failCount = collect($results)->reject(fn($r) => $r->success)->count();

        Log::info('PublishArticleJob completed', [
            'article_id' => $this->articleId,
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }
}
