<?php

namespace Bader\ContentPublisher\Extensions\TelegramApproval;

use Bader\ContentPublisher\Contracts\Extension;
use Illuminate\Contracts\Foundation\Application;

/**
 * Telegram Approval Extension.
 *
 * Adds an RSS → AI → Telegram → Pipeline workflow with human-in-the-loop
 * approval via Telegram inline buttons.
 *
 * This class implements the Extension contract so it can be registered
 * with the ExtensionManager alongside any custom extensions.
 */
class TelegramApprovalExtension implements Extension
{
    public function name(): string
    {
        return 'telegram-approval';
    }

    public function isEnabled(): bool
    {
        return (bool) config('scribe-ai.extensions.telegram_approval.enabled', false);
    }

    public function register(Application $app): void
    {
        $app->singleton(TelegramApprovalService::class);
        $app->singleton(CallbackHandler::class);
    }

    public function boot(Application $app): void
    {
        if ($app->runningInConsole()) {
            $app->make(\Illuminate\Contracts\Console\Kernel::class);

            // Commands are registered via the service provider
        }

        // Load webhook route — URL is auto-resolved from APP_URL if not set
        $routeFile = __DIR__ . '/../../../routes/telegram-webhook.php';

        if (file_exists($routeFile)) {
            $app->make('router')->middleware('api')->group($routeFile);
        }
    }
}
