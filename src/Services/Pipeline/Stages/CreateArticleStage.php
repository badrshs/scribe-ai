<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Enums\ArticleStatus;
use Bader\ContentPublisher\Events\ArticleCreated;
use Bader\ContentPublisher\Models\Article;
use Bader\ContentPublisher\Models\Tag;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pipeline stage: Create an Article model from the processed payload.
 *
 * Creates the article record, attaches tags (creating new ones as needed),
 * and marks the source StagedContent as published.
 */
class CreateArticleStage implements Pipe
{
    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        $pipeline = app(ContentPipeline::class);
        $pipeline->reportProgress('Create Article', 'started');

        if ($payload->rejected) {
            $pipeline->reportProgress('Create Article', 'skipped — content was rejected');

            return $payload;
        }

        $article = Article::query()->create([
            'title' => $payload->title ?? 'Untitled',
            'slug' => $payload->slug ?? Str::slug($payload->title ?? 'untitled'),
            'content' => $payload->content,
            'description' => $payload->description,
            'featured_image' => $payload->imagePath,
            'meta_title' => $payload->metaTitle,
            'meta_description' => $payload->metaDescription,
            'category_id' => $payload->categoryId,
            'status' => ArticleStatus::Published,
            'published_at' => now(),
        ]);

        $this->attachTags($article, $payload->tags);

        if ($payload->stagedContent) {
            $payload->stagedContent->markPublished();
        }

        Log::info('CreateArticleStage: article created', [
            'article_id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'tags_count' => count($payload->tags),
        ]);

        $pipeline->reportProgress('Create Article', 'completed — ID #' . $article->id);

        $newPayload = $payload->with(['article' => $article]);

        event(new ArticleCreated($newPayload, $article));

        return $next($newPayload);
    }

    /**
     * Attach tags to the article, creating new tags as needed.
     *
     * @param  string[]  $tagNames
     */
    protected function attachTags(Article $article, array $tagNames): void
    {
        if (empty($tagNames)) {
            return;
        }

        $tagIds = collect($tagNames)->map(function (string $name): int {
            return Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'slug' => Str::slug($name)],
            )->id;
        })->toArray();

        $article->tags()->sync($tagIds);
    }
}
