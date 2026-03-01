<?php

namespace Badr\ScribeAi\Console\Commands;

use Badr\ScribeAi\Models\PipelineRun;
use Illuminate\Console\Command;

/**
 * List recent pipeline runs and their status.
 */
class ListRunsCommand extends Command
{
    protected $signature = 'scribe:runs
        {--status= : Filter by status (pending, running, completed, failed, rejected)}
        {--limit=20 : Number of runs to display}';

    protected $description = 'List recent Scribe AI pipeline runs';

    public function handle(): int
    {
        $query = PipelineRun::query()->latest();

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $runs = $query->limit((int) $this->option('limit'))->get();

        if ($runs->isEmpty()) {
            $this->components->info('No pipeline runs found.');

            return self::SUCCESS;
        }

        $rows = $runs->map(fn(PipelineRun $run) => [
            $run->id,
            $this->statusBadge($run->status->value),
            mb_substr($run->source_url ?? '—', 0, 50),
            $run->current_stage_name ?? '—',
            $run->article_id ?? '—',
            $run->error_stage ? "{$run->error_stage}: " . mb_substr($run->error_message ?? '', 0, 40) : '—',
            $run->created_at?->diffForHumans() ?? '—',
        ])->toArray();

        $this->table(
            ['ID', 'Status', 'URL', 'Last Stage', 'Article', 'Error', 'Created'],
            $rows,
        );

        $failed = $runs->where('status.value', 'failed')->count();

        if ($failed > 0) {
            $this->newLine();
            $this->line("  <comment>{$failed} failed run(s)</comment> can be resumed with: <info>php artisan scribe:resume {id}</info>");
        }

        return self::SUCCESS;
    }

    protected function statusBadge(string $status): string
    {
        return match ($status) {
            'completed' => '<fg=green>✓ completed</>',
            'failed' => '<fg=red>✗ failed</>',
            'rejected' => '<fg=yellow>⊘ rejected</>',
            'running' => '<fg=cyan>● running</>',
            'pending' => '<fg=gray>○ pending</>',
            default => $status,
        };
    }
}
