<?php

namespace Badr\ScribeAi\Services\Publishing\Drivers;

use Badr\ScribeAi\Contracts\Publisher;
use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Publishes articles to Google Blogger via the Blogger API v3.
 *
 * Required config: blog_id, and credentials_path (for OAuth service account).
 *
 * @see https://developers.google.com/blogger/docs/3.0/reference
 */
class BloggerDriver implements Publisher
{
    protected ?string $accessToken = null;

    public function __construct(
        protected array $config = [],
    ) {}

    public function publish(Article $article, array $options = []): PublishResult
    {
        $blogId = $this->config['blog_id'] ?? throw new RuntimeException('Blogger blog_id not configured');

        $url = "https://www.googleapis.com/blogger/v3/blogs/{$blogId}/posts/";

        $postBody = $this->buildPostBody($article, $options);

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->post($url, $postBody);

        if ($response->failed()) {
            $error = $response->json('error.message', $response->body());

            Log::error('Blogger publish failed', [
                'article_id' => $article->id,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return PublishResult::failure($this->channel(), "Blogger API [{$response->status()}]: {$error}");
        }

        $postId = $response->json('id');
        $postUrl = $response->json('url');

        Log::info('Article published to Blogger', [
            'article_id' => $article->id,
            'blogger_post_id' => $postId,
            'blogger_url' => $postUrl,
        ]);

        return PublishResult::success(
            channel: $this->channel(),
            externalId: $postId,
            externalUrl: $postUrl,
        );
    }

    public function supports(Article $article): bool
    {
        return $article->isPublished() && ! empty($article->content);
    }

    public function channel(): string
    {
        return 'blogger';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPostBody(Article $article, array $options): array
    {
        $body = [
            'kind' => 'blogger#post',
            'title' => $options['title'] ?? $article->title,
            'content' => $options['content'] ?? $article->content,
        ];

        $labels = $this->buildLabels($article);
        if (! empty($labels)) {
            $body['labels'] = $labels;
        }

        if ($options['draft'] ?? false) {
            $body['status'] = 'DRAFT';
        }

        return $body;
    }

    /**
     * @return string[]
     */
    protected function buildLabels(Article $article): array
    {
        $labels = [];

        if ($article->category) {
            $labels[] = $article->category->name;
        }

        foreach ($article->tags as $tag) {
            $labels[] = $tag->name;
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        $token = $this->getAccessToken();
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    protected function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $credentialsPath = $this->config['credentials_path'] ?? null;
        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            return null;
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);
        if (! $credentials) {
            throw new RuntimeException('Invalid Google credentials file');
        }

        $this->accessToken = $this->fetchOAuthToken($credentials);

        return $this->accessToken;
    }

    /**
     * Exchange a service account key for an access token.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function fetchOAuthToken(array $credentials): string
    {
        $now = time();

        $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = base64url_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/blogger',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign(
            "{$header}.{$claim}",
            $signature,
            $credentials['private_key'],
            OPENSSL_ALGO_SHA256,
        );

        $jwt = "{$header}.{$claim}." . base64url_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Failed to obtain Google OAuth token: ' . $response->body());
        }

        return $response->json('access_token');
    }
}
