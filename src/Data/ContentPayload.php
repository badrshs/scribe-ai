<?php

namespace Badr\ScribeAi\Data;

use Badr\ScribeAi\Models\Article;
use Badr\ScribeAi\Models\StagedContent;

/**
 * Immutable payload that flows through the content pipeline.
 *
 * Each pipeline stage reads what it needs and returns a new instance
 * with updated fields via the fluent with() method.
 */
class ContentPayload
{
    public function __construct(
        public readonly ?string $sourceUrl = null,
        public readonly ?string $sourceDriver = null,
        public readonly ?string $rawContent = null,
        public readonly ?string $cleanedContent = null,
        public readonly ?string $title = null,
        public readonly ?string $content = null,
        public readonly ?string $description = null,
        public readonly ?string $slug = null,
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $imagePrompt = null,
        public readonly ?string $imagePath = null,
        public readonly ?int $categoryId = null,
        public readonly array $categories = [],
        public readonly array $tags = [],
        public readonly ?Article $article = null,
        public readonly ?StagedContent $stagedContent = null,
        public readonly bool $rejected = false,
        public readonly ?string $rejectionReason = null,
        public readonly array $publishResults = [],
        public readonly array $extra = [],
    ) {}

    /**
     * Create a new payload with the given fields overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): static
    {
        return new static(...array_merge($this->toArray(), $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sourceUrl' => $this->sourceUrl,
            'sourceDriver' => $this->sourceDriver,
            'rawContent' => $this->rawContent,
            'cleanedContent' => $this->cleanedContent,
            'title' => $this->title,
            'content' => $this->content,
            'description' => $this->description,
            'slug' => $this->slug,
            'metaTitle' => $this->metaTitle,
            'metaDescription' => $this->metaDescription,
            'imagePrompt' => $this->imagePrompt,
            'imagePath' => $this->imagePath,
            'categoryId' => $this->categoryId,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'article' => $this->article,
            'stagedContent' => $this->stagedContent,
            'rejected' => $this->rejected,
            'rejectionReason' => $this->rejectionReason,
            'publishResults' => $this->publishResults,
            'extra' => $this->extra,
        ];
    }

    /**
     * Create a payload from a staged content record.
     */
    public static function fromStagedContent(StagedContent $staged): static
    {
        return new static(
            sourceUrl: $staged->url,
            title: $staged->title,
            stagedContent: $staged,
            extra: [
                'source_name' => $staged->source_name,
                'category' => $staged->category,
            ],
        );
    }

    /**
     * Create a payload from a raw URL.
     */
    public static function fromUrl(string $url): static
    {
        return new static(sourceUrl: $url);
    }

    /**
     * Serialize the payload to a JSON-safe array (no Eloquent models).
     *
     * Used by PipelineRun to snapshot state for resume capability.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'sourceUrl' => $this->sourceUrl,
            'sourceDriver' => $this->sourceDriver,
            'rawContent' => $this->rawContent,
            'cleanedContent' => $this->cleanedContent,
            'title' => $this->title,
            'content' => $this->content,
            'description' => $this->description,
            'slug' => $this->slug,
            'metaTitle' => $this->metaTitle,
            'metaDescription' => $this->metaDescription,
            'imagePrompt' => $this->imagePrompt,
            'imagePath' => $this->imagePath,
            'categoryId' => $this->categoryId,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'rejected' => $this->rejected,
            'rejectionReason' => $this->rejectionReason,
            'extra' => $this->extra,
            // Models are stored by ID for rehydration
            'article_id' => $this->article?->id,
            'staged_content_id' => $this->stagedContent?->id,
        ];
    }

    /**
     * Reconstruct a payload from a snapshot (as stored in pipeline_runs).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): static
    {
        $article = isset($snapshot['article_id'])
            ? Article::query()->find($snapshot['article_id'])
            : null;

        $stagedContent = isset($snapshot['staged_content_id'])
            ? StagedContent::query()->find($snapshot['staged_content_id'])
            : null;

        return new static(
            sourceUrl: $snapshot['sourceUrl'] ?? null,
            sourceDriver: $snapshot['sourceDriver'] ?? null,
            rawContent: $snapshot['rawContent'] ?? null,
            cleanedContent: $snapshot['cleanedContent'] ?? null,
            title: $snapshot['title'] ?? null,
            content: $snapshot['content'] ?? null,
            description: $snapshot['description'] ?? null,
            slug: $snapshot['slug'] ?? null,
            metaTitle: $snapshot['metaTitle'] ?? null,
            metaDescription: $snapshot['metaDescription'] ?? null,
            imagePrompt: $snapshot['imagePrompt'] ?? null,
            imagePath: $snapshot['imagePath'] ?? null,
            categoryId: $snapshot['categoryId'] ?? null,
            categories: $snapshot['categories'] ?? [],
            tags: $snapshot['tags'] ?? [],
            article: $article,
            stagedContent: $stagedContent,
            rejected: $snapshot['rejected'] ?? false,
            rejectionReason: $snapshot['rejectionReason'] ?? null,
            extra: $snapshot['extra'] ?? [],
        );
    }
}
