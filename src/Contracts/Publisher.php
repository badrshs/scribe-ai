<?php

namespace Badr\ScribeAi\Contracts;

use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;

interface Publisher
{
    /**
     * Publish an article to this channel.
     */
    public function publish(Article $article, array $options = []): PublishResult;

    /**
     * Determine if the driver supports publishing the given article.
     */
    public function supports(Article $article): bool;

    /**
     * Get the unique channel name for this driver.
     */
    public function channel(): string;
}
