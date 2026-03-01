<?php

namespace Badr\ScribeAi\Events;

use Badr\ScribeAi\Data\ContentPayload;
use Badr\ScribeAi\Models\Article;
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
