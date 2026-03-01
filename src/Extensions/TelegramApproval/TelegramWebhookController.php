<?php

namespace Badr\ScribeAi\Extensions\TelegramApproval;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * HTTP controller for receiving Telegram webhook callbacks.
 *
 * Alternative to the polling command — use this when you have a publicly
 * accessible URL that Telegram can POST callbacks to.
 *
 * Setup:
 *   1. Set TELEGRAM_WEBHOOK_URL in .env
 *   2. Run: php artisan scribe:telegram-set-webhook
 *   3. Telegram will POST callbacks to your app automatically
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, CallbackHandler $handler): JsonResponse
    {
        // Verify secret token if configured
        $secret = config('scribe-ai.extensions.telegram_approval.webhook_secret');

        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            Log::warning('TelegramWebhook: invalid secret token');

            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();

        if (empty($update)) {
            return response()->json(['ok' => true]);
        }

        try {
            $result = $handler->handle($update);

            if ($result) {
                Log::info('TelegramWebhook: processed callback', $result);
            }
        } catch (\Throwable $e) {
            Log::error('TelegramWebhook: callback processing failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Always return 200 to Telegram to prevent retries
        return response()->json(['ok' => true]);
    }
}
