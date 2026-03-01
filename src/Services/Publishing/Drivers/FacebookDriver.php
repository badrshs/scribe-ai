<?php

namespace Badr\ScribeAi\Services\Publishing\Drivers;

use Badr\ScribeAi\Contracts\Publisher;
use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Publishes articles to a Facebook Page via the Graph API.
 *
 * Required config: page_id, access_token.
 * Optional config: api_version (default v21.0), timeout, retries.
 *
 * The access token must be a long-lived Page Access Token.
 *
 * @see https://developers.facebook.com/docs/pages/access-tokens
 */
class FacebookDriver implements Publisher
{
    public function __construct(
        protected array $config = [],
    ) {}

    public function publish(Article $article, array $options = []): PublishResult
    {
        $pageId = $this->config['page_id'] ?? throw new RuntimeException('Facebook page_id not configured');
        $accessToken = $this->config['access_token'] ?? throw new RuntimeException('Facebook access_token not configured');
        $apiVersion = $this->config['api_version'] ?? 'v21.0';
        $timeout = (int) ($this->config['timeout'] ?? 25);
        $maxRetries = (int) ($this->config['retries'] ?? 2);

        $message = $this->buildMessage($article, $options);
        $url = "https://graph.facebook.com/{$apiVersion}/{$pageId}/feed";

        $lastError = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($timeout)->post($url, [
                    'message' => $message,
                    'link' => $options['url'] ?? null,
                    'access_token' => $accessToken,
                ]);

                if ($response->successful()) {
                    $postId = $response->json('id');

                    Log::info('Article published to Facebook', [
                        'article_id' => $article->id,
                        'post_id' => $postId,
                        'attempt' => $attempt + 1,
                    ]);

                    return PublishResult::success(
                        channel: $this->channel(),
                        externalId: $postId,
                        externalUrl: "https://facebook.com/{$postId}",
                        metadata: ['attempt' => $attempt + 1],
                    );
                }

                $lastError = "HTTP {$response->status()}: " . $response->body();
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            if ($attempt < $maxRetries) {
                sleep(1);
            }
        }

        Log::error('Facebook publish failed after retries', [
            'article_id' => $article->id,
            'error' => $lastError,
            'retries' => $maxRetries,
        ]);

        return PublishResult::failure($this->channel(), $lastError ?? 'Unknown Facebook error');
    }

    public function supports(Article $article): bool
    {
        return $article->isPublished();
    }

    public function channel(): string
    {
        return 'facebook';
    }

    protected function buildMessage(Article $article, array $options): string
    {
        $parts = [];

        if ($article->category) {
            $parts[] = '#' . str_replace(' ', '_', $article->category->name);
        }

        $parts[] = $article->title;

        if (! empty($options['message'])) {
            $parts[] = $options['message'];
        }

        $tags = $article->tags->take(6)->pluck('name')->map(
            fn(string $name) => '#' . str_replace(' ', '_', $name)
        );

        if ($tags->isNotEmpty()) {
            $parts[] = $tags->implode(' ');
        }

        return implode("\n\n", $parts);
    }
}
