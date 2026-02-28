<?php

namespace Bader\ContentPublisher\Console\Commands;

use Bader\ContentPublisher\Models\PipelineRun;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Illuminate\Console\Command;

/**
 * Resume a previously failed pipeline run.
 */
class ResumeRunCommand extends Command
{
    protected $signature = 'scribe:resume
        {id : The pipeline run ID to resume}
        {--silent : Suppress progress output}';

    protected $description = 'Resume a failed Scribe AI pipeline run from where it left off';

    public function handle(): int
    {
        $runId = (int) $this->argument('id');
        $silent = $this->option('silent');

        $run = PipelineRun::query()->find($runId);

        if (! $run) {
            $this->components->error("Pipeline run #{$runId} not found.");

            return self::FAILURE;
        }

        if (! $run->isResumable()) {
            $this->components->error(
                "Run #{$runId} cannot be resumed (status: {$run->status->value})."
            );

            if ($run->status->isTerminal()) {
                $this->line("  This run has already {$run->status->value}.");
            }

            return self::FAILURE;
        }

        if (! $silent) {
            $this->newLine();
            $this->components->info("Resuming Pipeline Run #{$runId}");
            $this->line("  <comment>URL:</comment>          {$run->source_url}");
            $this->line("  <comment>Failed at:</comment>    {$run->error_stage}");
            $this->line("  <comment>Error:</comment>        {$run->error_message}");
            $this->line("  <comment>Resuming from:</comment> stage {$run->current_stage_index}");
            $this->newLine();
        }

        $stages = $run->stages ?? [];
        $stageLabels = array_map(fn($s) => PipelineRun::stageShortName($s), $stages);

        $pipeline = app(ContentPipeline::class);

        if (! $silent) {
            $pipeline->onProgress(function (string $stage, string $status) use ($stageLabels) {
                if ($status === 'started') {
                    $step = array_search($stage, $stageLabels);
                    $step = $step !== false ? $step + 1 : 0;
                    $total = count($stageLabels);

                    $this->line("  <fg=cyan>[{$step}/{$total}]</> <options=bold>{$stage}</>  …");
                } elseif ($stage === 'Pipeline') {
                    // Pipeline-level events
                    $this->line("  <fg=gray>{$status}</>");
                } else {
                    $icon = str_starts_with($status, 'completed') ? '<fg=green>✓</>'
                        : (str_starts_with($status, 'skipped') ? '<fg=yellow>⊘</>'
                            : (str_starts_with($status, 'rejected') ? '<fg=red>✗</>'
                                : (str_starts_with($status, 'failed') ? '<fg=red>✗</>'
                                    : '<fg=gray>•</>')));

                    $this->line("        {$icon} {$status}");
                }
            });
        }

        $started = microtime(true);
        $result = $pipeline->resume($run);
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
            $this->components->info("Pipeline run #{$runId} completed successfully");
            $this->line("  <comment>Title:</comment>      {$result->article?->title}");
            $this->line("  <comment>Article ID:</comment>  {$result->article?->id}");
            $this->line("  <fg=gray>Completed in {$elapsed}s</>");
        }

        return self::SUCCESS;
    }
}
