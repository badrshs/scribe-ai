<?php

namespace Badr\ScribeAi\Extensions\TelegramApproval;

use Illuminate\Console\Command;

/**
 * Set, remove, or inspect the Telegram webhook for the approval extension.
 */
class SetWebhookCommand extends Command
{
    protected $signature = 'scribe:telegram-set-webhook
        {--remove : Remove the webhook instead of setting it}
        {--info : Show current webhook status from Telegram}';

    protected $description = 'Set, remove, or inspect the Telegram webhook for approval callbacks';

    public function handle(TelegramApprovalService $telegram): int
    {
        if ($this->option('info')) {
            return $this->showWebhookInfo($telegram);
        }

        if ($this->option('remove')) {
            $telegram->deleteWebhook();
            $this->components->info('Webhook removed. Use scribe:telegram-poll for manual polling.');

            return self::SUCCESS;
        }

        $url = $telegram->resolveWebhookUrl();

        if (! $url) {
            $this->components->error(
                'No webhook URL could be resolved. Set TELEGRAM_WEBHOOK_URL or APP_URL in your .env.'
            );

            return self::FAILURE;
        }

        $secret = config('scribe-ai.extensions.telegram_approval.webhook_secret');

        $telegram->setWebhook($url, $secret);

        $this->components->info("Webhook set: {$url}");

        if ($secret) {
            $this->line('  <fg=gray>Secret token configured for verification.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Display the current webhook info from Telegram.
     */
    protected function showWebhookInfo(TelegramApprovalService $telegram): int
    {
        $info = $telegram->getWebhookInfo();

        if ($info === null) {
            $this->components->error('Failed to retrieve webhook info from Telegram.');

            return self::FAILURE;
        }

        $url = $info['url'] ?? '';

        if (empty($url)) {
            $this->components->warn('No webhook is currently set. Telegram is in polling mode.');
            $this->newLine();
            $this->line('  <fg=gray>Set a webhook with: php artisan scribe:telegram-set-webhook</>');

            return self::SUCCESS;
        }

        $this->components->info('Webhook is active');
        $this->newLine();

        $rows = [
            ['URL', $url],
            ['Has custom certificate', ($info['has_custom_certificate'] ?? false) ? 'Yes' : 'No'],
            ['Pending updates', $info['pending_update_count'] ?? 0],
            ['Max connections', $info['max_connections'] ?? 40],
            ['Allowed updates', implode(', ', $info['allowed_updates'] ?? ['all'])],
        ];

        if (! empty($info['last_error_date'])) {
            $rows[] = ['Last error', date('Y-m-d H:i:s', $info['last_error_date'])];
            $rows[] = ['Last error message', $info['last_error_message'] ?? 'Unknown'];
        }

        if (! empty($info['last_synchronization_error_date'])) {
            $rows[] = ['Last sync error', date('Y-m-d H:i:s', $info['last_synchronization_error_date'])];
        }

        $this->table(['Property', 'Value'], $rows);

        // Check if URL matches what we expect
        $expectedUrl = $telegram->resolveWebhookUrl();

        if ($expectedUrl && $url !== $expectedUrl) {
            $this->newLine();
            $this->components->warn("Webhook URL does not match your config.");
            $this->line("  <fg=gray>Expected: {$expectedUrl}</>");
            $this->line("  <fg=gray>Actual:   {$url}</>");
            $this->line('  <fg=gray>Run without --info to update it.</>');
        }

        return self::SUCCESS;
    }
}
