<?php

namespace Bader\ContentPublisher\Console\Commands;

use Bader\ContentPublisher\Jobs\ProcessContentPipelineJob;
use Bader\ContentPublisher\Models\StagedContent;
use Illuminate\Console\Command;

/**
 * Finds the first approved but unpublished staged content
 * and dispatches it through the content pipeline.
 */
class PublishApprovedCommand extends Command
{
    protected $signature = 'content:publish-approved
        {--limit=1 : Maximum number of articles to publish}';

    protected $description = 'Publish approved staged content through the pipeline';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $staged = StagedContent::query()
            ->readyToPublish()
            ->orderBy('approved_at')
            ->limit($limit)
            ->get();

        if ($staged->isEmpty()) {
            $this->info('No approved content waiting to be published.');

            return self::SUCCESS;
        }

        foreach ($staged as $item) {
            ProcessContentPipelineJob::dispatch($item->id);
            $this->info("Dispatched pipeline for: {$item->title}");
        }

        $this->info("Dispatched {$staged->count()} article(s) for processing.");

        return self::SUCCESS;
    }
}
