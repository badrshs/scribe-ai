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
        {--sync : Process synchronously instead of dispatching a job}
        {--silent : Suppress progress output}';

    protected $description = 'Process a URL through the Scribe AI content pipeline';

    public function handle(): int
    {
        $url = $this->argument('url');

        if ($this->option('sync')) {
            return $this->processSync($url);
        }

        ProcessContentPipelineJob::dispatch(url: $url);

        if (! $this->option('silent')) {
            $this->info("Pipeline job dispatched for: {$url}");
            $this->line('  The job will run in the background on the <comment>pipeline</comment> queue.');
            $this->line('  Use <comment>--sync</comment> to run inline and see live progress.');
        }

        return self::SUCCESS;
    }

    protected function processSync(string $url): int
    {
        $silent = $this->option('silent');

        if (! $silent) {
            $this->newLine();
            $this->components->info("Scribe AI — Processing URL");
            $this->line("  <comment>URL:</comment> {$url}");
            $this->newLine();
        }

        $started = microtime(true);
        $stageIndex = 0;
        $stageLabels = ['Scrape', 'AI Rewrite', 'Generate Image', 'Optimise Image', 'Create Article', 'Publish'];

        $pipeline = app(ContentPipeline::class);

        if (! $silent) {
            $pipeline->onProgress(function (string $stage, string $status) use (&$stageIndex, $stageLabels) {
                if ($status === 'started') {
                    $step = array_search($stage, $stageLabels);
                    $step = $step !== false ? $step + 1 : ++$stageIndex;
                    $total = count($stageLabels);

                    $this->line("  <fg=cyan>[{$step}/{$total}]</> <options=bold>{$stage}</>  …");
                } elseif ($stage === 'Pipeline') {
                    // Pipeline-level events are handled outside
                } else {
                    $icon = str_starts_with($status, 'completed') ? '<fg=green>✓</>' :
                           (str_starts_with($status, 'skipped')   ? '<fg=yellow>⊘</>' :
                           (str_starts_with($status, 'rejected')  ? '<fg=red>✗</>' :
                           (str_starts_with($status, 'failed')    ? '<fg=red>✗</>' : '<fg=gray>•</>')));

                    $this->line("        {$icon} {$status}");
                }
            });

            $this->line("  <fg=gray>ContentPayload created from URL</>");
            $this->line("  <fg=gray>Executing pipeline: " . implode(' → ', $stageLabels) . "</>");
            $this->newLine();
        }

        $payload = ContentPayload::fromUrl($url);
        $result = $pipeline->process($payload);

        $elapsed = round(microtime(true) - $started, 2);

        if ($result->rejected) {
            if (! $silent) {
                $this->newLine();
                $this->components->error("Content rejected: {$result->rejectionReason}");
                $this->line("  <fg=gray>Completed in {$elapsed}s</>");
            }

            return self::FAILURE;
        }

        if (! $silent) {
            $this->newLine();
            $this->components->info("Article created successfully");
            $this->line("  <comment>Title:</comment>      {$result->article?->title}");
            $this->line("  <comment>Article ID:</comment>  {$result->article?->id}");
            $this->line("  <comment>Slug:</comment>       {$result->article?->slug}");
            $this->line("  <comment>Image:</comment>      " . ($result->imagePath ?? 'none'));
            $this->line("  <comment>Published:</comment>   " . count($result->publishResults) . ' channel(s)');
            $this->line("  <fg=gray>Completed in {$elapsed}s</>");
        }

        return self::SUCCESS;
    }
}
