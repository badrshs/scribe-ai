<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Models\Category;
use Bader\ContentPublisher\Services\Ai\AiService;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pipeline stage: Use AI to rewrite, categorize, and enrich the content.
 *
 * Sends scraped content to the AI for processing and expects a structured
 * JSON response with title, content, metadata, category, tags, and
 * an image generation prompt.
 *
 * If the AI returns status: "reject", the pipeline halts.
 */
class AiRewriteStage implements Pipe
{
    public function __construct(
        protected AiService $ai,
    ) {}

    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        $pipeline = app(ContentPipeline::class);
        $pipeline->reportProgress('AI Rewrite', 'started');

        $content = $payload->cleanedContent ?? $payload->rawContent;

        if (! $content) {
            Log::warning('AiRewriteStage: no content to process, skipping');
            $pipeline->reportProgress('AI Rewrite', 'skipped — no content to process');

            return $next($payload);
        }

        $categories = Category::query()->pluck('name', 'id')->toArray();

        $systemPrompt = $this->buildSystemPrompt($categories);
        $userPrompt = "Process the following article content:\n\n<content>\n{$content}\n</content>";

        if ($payload->title) {
            $userPrompt = "Original title: {$payload->title}\n\n{$userPrompt}";
        }

        $result = $this->ai->completeJson($systemPrompt, $userPrompt);

        if (($result['status'] ?? '') === 'reject') {
            Log::info('AiRewriteStage: content rejected by AI', [
                'url' => $payload->sourceUrl,
                'reason' => $result['reason'] ?? 'No reason provided',
            ]);

            $pipeline->reportProgress('AI Rewrite', 'rejected — ' . ($result['reason'] ?? 'no reason'));

            return $payload->with([
                'rejected' => true,
                'rejectionReason' => $result['reason'] ?? 'Rejected by AI',
            ]);
        }

        $categoryId = $this->resolveCategory($result, $categories);

        Log::info('AiRewriteStage: content processed', [
            'title' => $result['title'] ?? 'untitled',
            'category_id' => $categoryId,
            'tags_count' => count($result['tags'] ?? []),
        ]);

        $pipeline->reportProgress('AI Rewrite', 'completed — "' . ($result['title'] ?? 'untitled') . '"');

        return $next($payload->with([
            'title' => $result['title'] ?? $payload->title,
            'content' => $result['content'] ?? $content,
            'description' => $result['description'] ?? null,
            'metaTitle' => $result['meta_title'] ?? null,
            'metaDescription' => $result['meta_description'] ?? null,
            'imagePrompt' => $result['image_prompt'] ?? null,
            'categoryId' => $categoryId,
            'tags' => $result['tags'] ?? [],
            'slug' => Str::slug($result['title'] ?? $payload->title ?? ''),
        ]));
    }

    /**
     * @param  array<int, string>  $categories
     */
    protected function buildSystemPrompt(array $categories): string
    {
        $categoryList = collect($categories)
            ->map(fn(string $name, int $id) => "  {$id}: {$name}")
            ->implode("\n");

        return <<<PROMPT
        You are a professional content editor and publisher. Process the provided article and return a JSON object with these fields:

        - "status": "accept" or "reject" (reject if content is low quality, spam, or irrelevant)
        - "reason": reason for rejection (only if status is "reject")
        - "title": An optimized, engaging article title (max 80 chars)
        - "content": The full article content as clean HTML
        - "description": A compelling short description (max 180 chars)
        - "meta_title": SEO meta title (max 60 chars)
        - "meta_description": SEO meta description (max 160 chars)
        - "image_prompt": A detailed prompt for generating a featured image
        - "category_id": The best matching category ID from the list below
        - "tags": Array of 3-6 relevant tag strings

        Available categories:
        {$categoryList}

        Ensure the content is well-structured, engaging, and optimized for web reading.
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<int, string>  $categories
     */
    protected function resolveCategory(array $result, array $categories): ?int
    {
        $categoryId = $result['category_id'] ?? null;

        if ($categoryId && isset($categories[$categoryId])) {
            return (int) $categoryId;
        }

        return array_key_first($categories);
    }
}
