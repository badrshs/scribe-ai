<?php

namespace Badr\ScribeAi\Services\Publishing\Drivers;

use Badr\ScribeAi\Contracts\Publisher;
use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;
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

        $imagePath = $article->image_path ?? $article->featured_image ?? null;
        $hasImage = $imagePath && $this->imageExists($imagePath);

        if ($hasImage) {
            $response = $this->sendPhoto($botToken, $chatId, $parseMode, $imagePath, $article, $options);
        } else {
            $response = $this->sendMessage($botToken, $chatId, $parseMode, $article, $options);
        }

        if ($response->failed()) {
            $error = $response->json('description', $response->body());

            // Fall back to text-only if photo send failed
            if ($hasImage) {
                Log::warning('Telegram photo send failed, falling back to text-only', [
                    'article_id' => $article->id,
                    'error' => $error,
                ]);

                $response = $this->sendMessage($botToken, $chatId, $parseMode, $article, $options);

                if ($response->failed()) {
                    $error = $response->json('description', $response->body());
                }
            }
        }

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
            'with_photo' => $hasImage && $response->successful(),
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

    /**
     * Send a text-only message via the Telegram Bot API.
     */
    protected function sendMessage(string $botToken, string $chatId, string $parseMode, Article $article, array $options): \Illuminate\Http\Client\Response
    {
        $text = $this->formatMessage($article, $options);

        return Http::timeout(15)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => false,
        ]);
    }

    /**
     * Send a photo with caption via the Telegram Bot API.
     *
     * Supports both local file paths and remote URLs.
     * The caption uses the same formatted message as text-only mode.
     */
    protected function sendPhoto(string $botToken, string $chatId, string $parseMode, string $imagePath, Article $article, array $options): \Illuminate\Http\Client\Response
    {
        $caption = $this->formatMessage($article, $options);

        // Telegram captions are limited to 1024 characters
        if (mb_strlen($caption) > 1024) {
            $caption = mb_substr($caption, 0, 1021) . '...';
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";

        // If it's a URL, send directly; if it's a local file, upload as multipart
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return Http::timeout(30)->post($url, [
                'chat_id' => $chatId,
                'photo' => $imagePath,
                'caption' => $caption,
                'parse_mode' => $parseMode,
            ]);
        }

        // Resolve local path from storage
        $absolutePath = $this->resolveImagePath($imagePath);

        if (! $absolutePath || ! file_exists($absolutePath)) {
            // Can't find the file - fall back to text by returning a failed-like response
            Log::warning('Telegram: image file not found, sending text-only', [
                'image_path' => $imagePath,
            ]);

            return $this->sendMessage($botToken, $chatId, $parseMode, $article, $options);
        }

        return Http::timeout(30)
            ->attach('photo', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post($url, [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => $parseMode,
            ]);
    }

    /**
     * Check if an image is available (exists as URL or local file).
     */
    protected function imageExists(string $imagePath): bool
    {
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return true;
        }

        $absolutePath = $this->resolveImagePath($imagePath);

        return $absolutePath && file_exists($absolutePath);
    }

    /**
     * Resolve a relative image path to an absolute filesystem path.
     */
    protected function resolveImagePath(string $imagePath): ?string
    {
        // Already absolute
        if (file_exists($imagePath)) {
            return $imagePath;
        }

        // Try storage path
        $storagePath = storage_path("app/public/{$imagePath}");
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        // Try public path
        $publicPath = public_path($imagePath);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        return null;
    }
}
