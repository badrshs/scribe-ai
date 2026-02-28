<?php

namespace Bader\ContentPublisher\Services\Publishing\Drivers;

use Bader\ContentPublisher\Contracts\Publisher;
use Bader\ContentPublisher\Data\PublishResult;
use Bader\ContentPublisher\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Publishes articles to a Telegram channel/chat via the Bot API.
 *
 * Required config: bot_token, chat_id.
 * Optional config: parse_mode (default HTML).
 */
class TelegramDriver implements Publisher
{
    public function __construct(
        protected array $config = [],
    ) {}

    public function publish(Article $article, array $options = []): PublishResult
    {
        $botToken = $this->config['bot_token'] ?? throw new RuntimeException('Telegram bot_token not configured');
        $chatId = $this->config['chat_id'] ?? throw new RuntimeException('Telegram chat_id not configured');
        $parseMode = $this->config['parse_mode'] ?? 'HTML';

        $text = $this->formatMessage($article, $options);
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $response = Http::timeout(15)->post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => false,
        ]);

        if ($response->failed()) {
            $error = $response->json('description', $response->body());

            Log::error('Telegram publish failed', [
                'article_id' => $article->id,
                'error' => $error,
            ]);

            return PublishResult::failure($this->channel(), $error);
        }

        $messageId = $response->json('result.message_id');

        Log::info('Article published to Telegram', [
            'article_id' => $article->id,
            'message_id' => $messageId,
        ]);

        return PublishResult::success(
            channel: $this->channel(),
            externalId: (string) $messageId,
            metadata: ['chat_id' => $chatId],
        );
    }

    public function supports(Article $article): bool
    {
        return $article->isPublished();
    }

    public function channel(): string
    {
        return 'telegram';
    }

    protected function formatMessage(Article $article, array $options): string
    {
        $parts = [];

        $parts[] = "<b>{$article->title}</b>";

        if ($article->description) {
            $parts[] = Str::limit(strip_tags($article->description), 200);
        }

        if (! empty($options['url'])) {
            $parts[] = $options['url'];
        }

        return implode("\n\n", $parts);
    }
}
