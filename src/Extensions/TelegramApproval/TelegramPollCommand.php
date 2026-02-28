<?php

namespace Bader\ContentPublisher\Extensions\TelegramApproval;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Long-polls Telegram for approval callbacks and processes them.
 *
 * This is the Phase 2 entry point for environments that cannot use
 * webhooks (local dev, firewalled servers, etc.).
 *
 * Run it in the foreground or as a supervised process:
 *   php artisan scribe:telegram-poll
 *   php artisan scribe:telegram-poll --once   # process pending then exit
 */
class TelegramPollCommand extends Command
{
    protected $signature = 'scribe:telegram-poll
        {--once : Process pending callbacks and exit (no long-poll loop)}
        {--timeout=30 : Long-poll timeout in seconds}
        {--silent : Suppress console output}';

    protected $description = 'Poll Telegram for approval/rejection callbacks';

    public function handle(
        TelegramApprovalService $telegram,
        CallbackHandler $handler,
    ): int {
        $once = (bool) $this->option('once');
        $timeout = (int) $this->option('timeout');
        $silent = (bool) $this->option('silent');

        if (! $silent) {
            $this->components->info('Scribe AI — Telegram Approval Poller');

            if ($once) {
                $this->line('  <fg=gray>Mode: single pass (--once)</>');
            } else {
                $this->line('  <fg=gray>Mode: continuous long-poll (Ctrl+C to stop)</>');
            }

            $this->newLine();
        }

        $offset = 0;
        $processed = 0;

        do {
            try {
                $updates = $telegram->getUpdates($offset, $timeout);
            } catch (\Throwable $e) {
                Log::error('TelegramPoll: getUpdates failed', ['error' => $e->getMessage()]);

                if (! $silent) {
                    $this->line("  <fg=red>✗</> Poll error: {$e->getMessage()}");
                }

                // Back off before retrying
                if (! $once) {
                    sleep(5);

                    continue;
                }

                return self::FAILURE;
            }

            foreach ($updates as $update) {
                // Move offset past this update
                $offset = ($update['update_id'] ?? 0) + 1;

                $result = $handler->handle($update);

                if (! $result) {
                    continue;
                }

                $processed++;

                $icon = $result['action'] === 'approved' ? '<fg=green>✅</>' : '<fg=red>❌</>';

                if (! $silent) {
                    $this->line("  {$icon} {$result['action']}: <options=bold>{$result['title']}</> (#{$result['staged_content_id']})");
                }
            }

            // If --once and we got no updates, we're done
            if ($once && empty($updates)) {
                break;
            }

            // If --once and we processed updates, do one more pass to catch stragglers
            if ($once && ! empty($updates)) {
                continue;
            }
        } while (true);

        if (! $silent) {
            $this->newLine();
            $this->components->info("Processed {$processed} decision(s).");
        }

        return self::SUCCESS;
    }
}
