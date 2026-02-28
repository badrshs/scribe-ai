<?php

namespace Bader\ContentPublisher\Extensions\TelegramApproval;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends RSS entries to Telegram with inline approve/reject buttons
 * and processes the callback responses.
 *
 * This is the core service of the Telegram Approval extension.
 * It uses the Telegram Bot API's inline keyboard feature so
 * the human reviewer can approve or reject directly from the chat.
 */
class TelegramApprovalService
{
    protected string $botToken;

    protected string $chatId;

    protected string $baseUrl;

    public function __construct()
    {
        $this->botToken = config('scribe-ai.extensions.telegram_approval.bot_token')
            ?? config('scribe-ai.drivers.telegram.bot_token')
            ?? throw new RuntimeException('Telegram bot_token not configured for approval extension');

        $this->chatId = config('scribe-ai.extensions.telegram_approval.chat_id')
            ?? config('scribe-ai.drivers.telegram.chat_id')
            ?? throw new RuntimeException('Telegram chat_id not configured for approval extension');

        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send an RSS entry to Telegram for human review.
     *
     * The inline keyboard contains approve/reject buttons with
     * callback data encoding the staged_content ID.
     */
    public function sendForApproval(int $stagedContentId, string $title, string $url, ?string $summary = null, ?string $category = null): int
    {
        $lines = ["<b>📰 New Article for Review</b>"];
        $lines[] = '';
        $lines[] = "<b>Title:</b> " . e($title);

        if ($category) {
            $lines[] = "<b>Category:</b> " . e($category);
        }

        if ($summary) {
            $lines[] = '';
            $lines[] = "<b>Summary:</b>";
            $lines[] = e($summary);
        }

        $lines[] = '';
        $lines[] = "<b>Source:</b> {$url}";

        $text = implode("\n", $lines);

        $response = Http::timeout(15)->post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Approve', 'callback_data' => "scribe_approve:{$stagedContentId}"],
                        ['text' => '❌ Reject', 'callback_data' => "scribe_reject:{$stagedContentId}"],
                    ],
                ],
            ]),
        ]);

        if ($response->failed()) {
            $error = $response->json('description', $response->body());

            throw new RuntimeException("Telegram sendMessage failed: {$error}");
        }

        $messageId = (int) $response->json('result.message_id');

        Log::info('TelegramApproval: sent for review', [
            'staged_content_id' => $stagedContentId,
            'message_id' => $messageId,
            'title' => $title,
        ]);

        return $messageId;
    }

    /**
     * Answer a callback query (removes the "loading" spinner in Telegram).
     */
    public function answerCallback(string $callbackQueryId, string $text): void
    {
        Http::timeout(10)->post("{$this->baseUrl}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => false,
        ]);
    }

    /**
     * Edit the original message to reflect the decision (removes buttons).
     */
    public function editMessageDecision(int $messageId, string $decision, string $title): void
    {
        $icon = $decision === 'approved' ? '✅' : '❌';
        $label = $decision === 'approved' ? 'APPROVED' : 'REJECTED';

        $text = "{$icon} <b>{$label}</b>\n\n<b>{$title}</b>";

        Http::timeout(10)->post("{$this->baseUrl}/editMessageText", [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * Fetch pending callback updates from Telegram via long-polling.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        $response = Http::timeout($timeout + 5)->post("{$this->baseUrl}/getUpdates", [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['callback_query'],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Telegram getUpdates failed: ' . $response->body());
        }

        return $response->json('result', []);
    }

    /**
     * Set the webhook URL for receiving Telegram callbacks.
     */
    public function setWebhook(string $url, ?string $secretToken = null): bool
    {
        $payload = [
            'url' => $url,
            'allowed_updates' => ['callback_query'],
        ];

        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }

        $response = Http::timeout(15)->post("{$this->baseUrl}/setWebhook", $payload);

        if ($response->failed()) {
            throw new RuntimeException('Telegram setWebhook failed: ' . $response->body());
        }

        Log::info('TelegramApproval: webhook set', ['url' => $url]);

        return true;
    }

    /**
     * Remove the webhook (switch back to polling mode).
     */
    public function deleteWebhook(): bool
    {
        $response = Http::timeout(10)->post("{$this->baseUrl}/deleteWebhook");

        return $response->successful();
    }

    /**
     * Parse callback data string into action + staged_content_id.
     *
     * @return array{action: string, staged_content_id: int}|null
     */
    public function parseCallbackData(string $data): ?array
    {
        if (! preg_match('/^scribe_(approve|reject):(\d+)$/', $data, $matches)) {
            return null;
        }

        return [
            'action' => $matches[1],
            'staged_content_id' => (int) $matches[2],
        ];
    }
}
