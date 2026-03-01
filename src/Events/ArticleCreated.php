<?php

namespace Bader\ContentPublisher\Events;

use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Models\Article;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the CreateArticleStage persists a new article to the database.
 */
class ArticleCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly Article $article,
    ) {}
}
