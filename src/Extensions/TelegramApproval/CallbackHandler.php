<?php

namespace Bader\ContentPublisher\Extensions\TelegramApproval;

use Bader\ContentPublisher\Jobs\ProcessContentPipelineJob;
use Bader\ContentPublisher\Models\StagedContent;
use Illuminate\Support\Facades\Log;

/**
 * Processes Telegram callback queries (approve/reject decisions).
 *
 * When a user taps ✅ Approve on a Telegram message, this handler:
 * 1. Marks the StagedContent as approved
 * 2. Updates the Telegram message to show the decision
 * 3. Dispatches the full content pipeline using the article URL
 *
 * On ❌ Reject it simply marks it and updates the message.
 */
class CallbackHandler
{
    public function __construct(
        protected TelegramApprovalService $telegram,
    ) {}

    /**
     * Process a single callback update from Telegram.
     *
     * @param  array<string, mixed>  $update  Raw Telegram update object
     * @return array{action: string, staged_content_id: int, title: string}|null
     */
    public function handle(array $update): ?array
    {
        $callback = $update['callback_query'] ?? null;

        if (! $callback) {
            return null;
        }

        $data = $this->telegram->parseCallbackData($callback['data'] ?? '');

        if (! $data) {
            return null;
        }

        $staged = StagedContent::query()->find($data['staged_content_id']);

        if (! $staged) {
            $this->telegram->answerCallback($callback['id'], '⚠ Entry not found');

            return null;
        }

        // Already processed?
        if ($staged->approved || $staged->published) {
            $this->telegram->answerCallback($callback['id'], 'ℹ Already processed');

            return null;
        }

        $action = $data['action'];
        $messageId = $callback['message']['message_id'] ?? null;

        if ($action === 'approve') {
            return $this->handleApproval($staged, $callback['id'], $messageId);
        }

        return $this->handleRejection($staged, $callback['id'], $messageId);
    }

    /**
     * Approve the staged content and dispatch the pipeline.
     *
     * @return array{action: string, staged_content_id: int, title: string}
     */
    protected function handleApproval(StagedContent $staged, string $callbackId, ?int $messageId): array
    {
        $staged->markApproved();

        $this->telegram->answerCallback($callbackId, '✅ Approved! Pipeline starting…');

        if ($messageId) {
            $this->telegram->editMessageDecision($messageId, 'approved', $staged->title);
        }

        // Dispatch the full pipeline using the web driver (we have the URL)
        ProcessContentPipelineJob::dispatch(
            stagedContentId: $staged->id,
            url: $staged->url,
            sourceDriver: 'web',
        );

        Log::info('TelegramApproval: approved and pipeline dispatched', [
            'staged_content_id' => $staged->id,
            'title' => $staged->title,
            'url' => $staged->url,
        ]);

        return [
            'action' => 'approved',
            'staged_content_id' => $staged->id,
            'title' => $staged->title,
        ];
    }

    /**
     * Reject the staged content.
     *
     * @return array{action: string, staged_content_id: int, title: string}
     */
    protected function handleRejection(StagedContent $staged, string $callbackId, ?int $messageId): array
    {
        // Mark as processed but not approved (stays approved=false)
        $staged->update(['processed_at' => now()]);

        $this->telegram->answerCallback($callbackId, '❌ Rejected');

        if ($messageId) {
            $this->telegram->editMessageDecision($messageId, 'rejected', $staged->title);
        }

        Log::info('TelegramApproval: rejected', [
            'staged_content_id' => $staged->id,
            'title' => $staged->title,
        ]);

        return [
            'action' => 'rejected',
            'staged_content_id' => $staged->id,
            'title' => $staged->title,
        ];
    }
}
