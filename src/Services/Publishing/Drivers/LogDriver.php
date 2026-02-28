<?php

namespace Bader\ContentPublisher\Services\Publishing\Drivers;

use Bader\ContentPublisher\Contracts\Publisher;
use Bader\ContentPublisher\Data\PublishResult;
use Bader\ContentPublisher\Models\Article;
use Illuminate\Support\Facades\Log;

/**
 * Logs publish operations instead of sending them anywhere.
 *
 * Useful for development, testing, and debugging the pipeline.
 */
class LogDriver implements Publisher
{
    public function __construct(
        protected array $config = [],
    ) {}

    public function publish(Article $article, array $options = []): PublishResult
    {
        $level = $this->config['level'] ?? 'info';
        $channel = $this->config['channel'] ?? null;

        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->{$level}('Article published (log driver)', [
            'article_id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'status' => $article->status->value,
            'options' => $options,
        ]);

        return PublishResult::success(
            channel: $this->channel(),
            externalId: 'log-' . $article->id,
            metadata: ['driver' => 'log', 'level' => $level],
        );
    }

    public function supports(Article $article): bool
    {
        return true;
    }

    public function channel(): string
    {
        return 'log';
    }
}
