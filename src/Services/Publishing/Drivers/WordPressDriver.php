<?php

namespace Badr\ScribeAi\Services\Publishing\Drivers;

use Badr\ScribeAi\Contracts\Publisher;
use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Publishes articles to a WordPress site via the REST API (v2).
 *
 * Supports Application Passwords (native WP 5.6+) and JWT Authentication.
 *
 * Required config: url, username, password (application password).
 * Optional config: default_status (publish/draft/pending), timeout.
 *
 * @see https://developer.wordpress.org/rest-api/reference/posts/
 */
class WordPressDriver implements Publisher
{
    public function __construct(
        protected array $config = [],
    ) {}

    public function publish(Article $article, array $options = []): PublishResult
    {
        $baseUrl = rtrim($this->config['url'] ?? '', '/');
        if (! $baseUrl) {
            throw new RuntimeException('WordPress URL not configured');
        }

        $endpoint = $baseUrl . '/wp-json/wp/v2/posts';
        $timeout = (int) ($this->config['timeout'] ?? 30);

        $postData = $this->buildPostData($article, $options);

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout($timeout)
            ->post($endpoint, $postData);

        if ($response->failed()) {
            $error = $response->json('message', $response->body());

            Log::error('WordPress publish failed', [
                'article_id' => $article->id,
                'status' => $response->status(),
                'error' => $error,
            ]);

            return PublishResult::failure(
                $this->channel(),
                "WordPress API [{$response->status()}]: {$error}",
            );
        }

        $postId = $response->json('id');
        $postUrl = $response->json('link');

        Log::info('Article published to WordPress', [
            'article_id' => $article->id,
            'wp_post_id' => $postId,
            'wp_url' => $postUrl,
        ]);

        return PublishResult::success(
            channel: $this->channel(),
            externalId: (string) $postId,
            externalUrl: $postUrl,
        );
    }

    public function supports(Article $article): bool
    {
        return $article->isPublished() && ! empty($article->content);
    }

    public function channel(): string
    {
        return 'wordpress';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPostData(Article $article, array $options): array
    {
        $data = [
            'title' => $options['title'] ?? $article->title,
            'content' => $options['content'] ?? $article->content,
            'status' => $options['status'] ?? $this->config['default_status'] ?? 'publish',
            'slug' => $article->slug,
        ];

        if ($article->description) {
            $data['excerpt'] = $article->description;
        }

        $categoryIds = $this->resolveCategoryIds($article, $options);
        if (! empty($categoryIds)) {
            $data['categories'] = $categoryIds;
        }

        $tagIds = $this->resolveTagIds($article, $options);
        if (! empty($tagIds)) {
            $data['tags'] = $tagIds;
        }

        if ($article->featured_image) {
            $mediaId = $this->uploadFeaturedImage($article);
            if ($mediaId) {
                $data['featured_media'] = $mediaId;
            }
        }

        return $data;
    }

    /**
     * Resolve WordPress category IDs. Creates categories if they don't exist.
     *
     * @return int[]
     */
    protected function resolveCategoryIds(Article $article, array $options): array
    {
        if (! empty($options['category_ids'])) {
            return $options['category_ids'];
        }

        if (! $article->category) {
            return [];
        }

        $wpCategoryId = $this->findOrCreateTerm('categories', $article->category->name);

        return $wpCategoryId ? [$wpCategoryId] : [];
    }

    /**
     * Resolve WordPress tag IDs. Creates tags if they don't exist.
     *
     * @return int[]
     */
    protected function resolveTagIds(Article $article, array $options): array
    {
        if (! empty($options['tag_ids'])) {
            return $options['tag_ids'];
        }

        return $article->tags
            ->map(fn($tag) => $this->findOrCreateTerm('tags', $tag->name))
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Find or create a taxonomy term (category or tag) in WordPress.
     */
    protected function findOrCreateTerm(string $taxonomy, string $name): ?int
    {
        $baseUrl = rtrim($this->config['url'] ?? '', '/');
        $endpoint = $baseUrl . "/wp-json/wp/v2/{$taxonomy}";

        $search = Http::withHeaders($this->buildHeaders())
            ->timeout(15)
            ->get($endpoint, ['search' => $name, 'per_page' => 1]);

        if ($search->successful()) {
            $results = $search->json();
            foreach ($results as $term) {
                if (Str::lower($term['name']) === Str::lower($name)) {
                    return $term['id'];
                }
            }
        }

        $create = Http::withHeaders($this->buildHeaders())
            ->timeout(15)
            ->post($endpoint, ['name' => $name]);

        if ($create->successful()) {
            return $create->json('id');
        }

        Log::warning("WordPress: could not resolve {$taxonomy} term", ['name' => $name]);

        return null;
    }

    /**
     * Upload the featured image to WordPress media library.
     */
    protected function uploadFeaturedImage(Article $article): ?int
    {
        $disk = config('scribe-ai.images.disk', 'public');
        $fullPath = Storage::disk($disk)->path($article->featured_image);

        if (! file_exists($fullPath)) {
            return null;
        }

        $baseUrl = rtrim($this->config['url'] ?? '', '/');
        $endpoint = $baseUrl . '/wp-json/wp/v2/media';

        $filename = basename($fullPath);
        $mimeType = mime_content_type($fullPath) ?: 'image/webp';

        $response = Http::withHeaders(array_merge($this->buildHeaders(), [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Type' => $mimeType,
        ]))
            ->timeout(60)
            ->withBody(file_get_contents($fullPath), $mimeType)
            ->post($endpoint);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::warning('WordPress: featured image upload failed', [
            'article_id' => $article->id,
            'error' => $response->json('message', $response->body()),
        ]);

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if ($username && $password) {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
        }

        return $headers;
    }
}
