<?php

namespace Badr\ScribeAi\Console\Commands;

use Badr\ScribeAi\Jobs\PublishArticleJob;
use Badr\ScribeAi\Models\Article;
use Illuminate\Console\Command;

/**
 * Publish an existing article to specified channels.
 */
class PublishArticleCommand extends Command
{
    protected $signature = 'scribe:publish
        {article : Article ID to publish}
        {--channels= : Comma-separated channel names (default: all active)}';

    protected $description = 'Publish an article to configured channels';

    public function handle(): int
    {
        $articleId = (int) $this->argument('article');

        $article = Article::query()->find($articleId);
        if (! $article) {
            $this->error("Article #{$articleId} not found.");

            return self::FAILURE;
        }

        if (! $article->isPublished()) {
            $this->error('Article is not in published status.');

            return self::FAILURE;
        }

        $channels = null;
        if ($this->option('channels')) {
            $channels = explode(',', $this->option('channels'));
        }

        PublishArticleJob::dispatch($articleId, $channels);
        $this->info("Publish job dispatched for article #{$articleId}: {$article->title}");

        return self::SUCCESS;
    }
}
