<?php

namespace Bader\ContentPublisher\Console\Commands;

use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Jobs\ProcessContentPipelineJob;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Illuminate\Console\Command;

/**
 * Process a URL directly through the content pipeline.
 */
class ProcessUrlCommand extends Command
{
    protected $signature = 'scribe:process-url
        {url : The URL to fetch and process}
        {--sync : Process synchronously instead of dispatching a job}';

    protected $description = 'Process a URL through the Scribe AI content pipeline';

    public function handle(): int
    {
        $url = $this->argument('url');

        if ($this->option('sync')) {
            $pipeline = app(ContentPipeline::class);
            $result = $pipeline->process(ContentPayload::fromUrl($url));

            if ($result->rejected) {
                $this->warn("Content was rejected: {$result->rejectionReason}");

                return self::FAILURE;
            }

            $this->info("Article created: {$result->article?->title}");

            return self::SUCCESS;
        }

        ProcessContentPipelineJob::dispatch(url: $url);
        $this->info("Pipeline job dispatched for: {$url}");

        return self::SUCCESS;
    }
}
