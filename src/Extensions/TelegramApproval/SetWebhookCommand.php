<?php

namespace Bader\ContentPublisher\Extensions\TelegramApproval;

use Illuminate\Console\Command;

/**
 * Set or remove the Telegram webhook for the approval extension.
 */
class SetWebhookCommand extends Command
{
    protected $signature = 'scribe:telegram-set-webhook
        {--remove : Remove the webhook instead of setting it}';

    protected $description = 'Set or remove the Telegram webhook for approval callbacks';

    public function handle(TelegramApprovalService $telegram): int
    {
        if ($this->option('remove')) {
            $telegram->deleteWebhook();
            $this->components->info('Webhook removed. Use scribe:telegram-poll for manual polling.');

            return self::SUCCESS;
        }

        $url = config('scribe-ai.extensions.telegram_approval.webhook_url');

        if (! $url) {
            $this->components->error('Set TELEGRAM_WEBHOOK_URL in your .env first.');

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
}
