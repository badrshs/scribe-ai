<?php

namespace Badr\ScribeAi\Console\Commands;

use Badr\ScribeAi\Data\ContentPayload;
use Badr\ScribeAi\Jobs\ProcessContentPipelineJob;
use Badr\ScribeAi\Services\Pipeline\ContentPipeline;
use Illuminate\Console\Command;

/**
 * Process a URL directly through the content pipeline.
 */
class ProcessUrlCommand extends Command
{
    protected $signature = 'scribe:process-url
        {url : The URL to fetch and process}
        {--sync : Process synchronously instead of dispatching a job}
        {--silent : Suppress progress output}
        {--source= : Force a specific content-source driver (web, rss, text)}
        {--categories= : Comma-separated id:name pairs (e.g. "1:Tech,2:Health")}';

    protected $description = 'Process a URL through the Scribe AI content pipeline';

    public function handle(): int
    {
        $url = $this->argument('url');
        $categories = $this->parseCategories($this->option('categories'));
        $source = $this->option('source');

        if ($this->option('sync')) {
            return $this->processSync($url, $categories, $source);
        }

        ProcessContentPipelineJob::dispatch(url: $url, categories: $categories, sourceDriver: $source);

        if (! $this->option('silent')) {
            $this->info("Pipeline job dispatched for: {$url}");
            $this->line('  The job will run in the background on the <comment>pipeline</comment> queue.');
            $this->line('  Use <comment>--sync</comment> to run inline and see live progress.');
        }

        return self::SUCCESS;
    }

    protected function processSync(string $url, array $categories = [], ?string $source = null): int
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
                    $icon = str_starts_with($status, 'completed') ? '<fg=green>✓</>' : (str_starts_with($status, 'skipped')   ? '<fg=yellow>⊘</>' : (str_starts_with($status, 'rejected')  ? '<fg=red>✗</>' : (str_starts_with($status, 'failed')    ? '<fg=red>✗</>' : '<fg=gray>•</>')));

                    $this->line("        {$icon} {$status}");
                }
            });

            $this->line("  <fg=gray>ContentPayload created from URL</>");
            $this->line("  <fg=gray>Executing pipeline: " . implode(' → ', $stageLabels) . "</>");
            $this->newLine();
        }

        $payload = ContentPayload::fromUrl($url);

        if ($source) {
            $payload = $payload->with(['sourceDriver' => $source]);
        }

        if (! empty($categories)) {
            $payload = $payload->with(['categories' => $categories]);
        }

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

    /**
     * Parse "1:Tech,2:Health" into [1 => 'Tech', 2 => 'Health'].
     *
     * @return array<int, string>
     */
    protected function parseCategories(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        $categories = [];

        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', trim($pair), 2);

            if (count($parts) === 2 && is_numeric($parts[0])) {
                $categories[(int) $parts[0]] = trim($parts[1]);
            }
        }

        return $categories;
    }
}
