<?php

use Badr\ScribeAi\Extensions\TelegramApproval\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram Approval Webhook Route
|--------------------------------------------------------------------------
|
| This route receives callback queries from the Telegram Bot API.
| It is only loaded when the Telegram Approval extension is enabled
| and a webhook URL is configured.
|
| The route path can be customised via:
|   TELEGRAM_WEBHOOK_PATH=/api/scribe/telegram/webhook
|
*/

$path = config(
    'scribe-ai.extensions.telegram_approval.webhook_path',
    'api/scribe/telegram/webhook'
);

Route::post($path, TelegramWebhookController::class)
    ->middleware('api')
    ->name('scribe.telegram.webhook');
