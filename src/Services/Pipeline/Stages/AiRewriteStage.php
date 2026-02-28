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
            $reasons = $result['reject_reasons'] ?? [$result['reason'] ?? 'No reason provided'];
            $reasonText = is_array($reasons) ? implode('; ', $reasons) : $reasons;

            Log::info('AiRewriteStage: content rejected by AI', [
                'url' => $payload->sourceUrl,
                'reasons' => $reasons,
            ]);

            $pipeline->reportProgress('AI Rewrite', 'rejected — ' . $reasonText);

            return $payload->with([
                'rejected' => true,
                'rejectionReason' => $reasonText,
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
            'imagePrompt' => $result['text-to-image'] ?? $result['image_prompt'] ?? null,
            'categoryId' => $categoryId,
            'tags' => $result['tags'] ?? [],
            'slug' => Str::slug($result['title'] ?? $payload->title ?? ''),
        ]));
    }

    /**
     * Build the editorial system prompt.
     *
     * The prompt is always in English. The configured output language
     * determines what language the AI writes the article in.
     *
     * @param  array<int, string>  $categories
     */
    protected function buildSystemPrompt(array $categories): string
    {
        $language = config('scribe-ai.ai.output_language', 'English');
        $isRtl = in_array(strtolower($language), ['arabic', 'hebrew', 'persian', 'urdu']);

        $categoriesJson = json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $rtlDirective = $isRtl
            ? "\n- The \"content\" field MUST be full RTL HTML. Wrap the output in a container with dir=\"rtl\" and lang attribute matching the target language (e.g. lang=\"ar\" for Arabic)."
            : '';

        return <<<PROMPT
You are a senior editor and expert rewriter. Your task is to extract the article from the provided input (which may be raw text, HTML, or noisy scraped text), clean it up, then translate and rewrite it in {$language} in a professional, original style, while checking whether it is suitable for publication. Return ONLY a single valid JSON object with the structure described below.

# Input
You will receive one of the following:
  1) Full HTML of a page, or
  2) Raw article text, or
  3) Text mixed with scraping noise (links, comments, menus, footers, etc.).
You will also receive a category list as JSON:
  {$categoriesJson}

# Goal
- Transform the original article into clear, professional, publish-ready content in {$language}, preserving the full structural meaning, after removing any elements that are not part of the article body (scraping artifacts).
- Ensure the output does not read like a literal translation or a copy.

# Writing Style
- Modern standard {$language}, informative and neutral tone, smooth short-to-medium sentences, avoid awkwardness and filler, avoid unnecessary loanwords.
- Genuine rewriting: change sentence structures, use precise synonyms, break up long sentences, remove padding.
- Do not mention sources, websites, slogans, or phrases like "according to [website]..." or "click here".

# Content Extraction and Cleaning (Critical)
When receiving HTML or noisy text:
1) Extract only the article body (title, subheadings, paragraphs, lists, tables, quotes related to the topic).
2) Remove entirely: sidebars, navigation bars, comments, promotional boxes, forms, share buttons, "related articles", "read also", copyright notices, footer/header, ads, scripts/code, marketing capture text, author/bio boxes, duplicate headings/sections, legal disclaimers unrelated to the core content, any content in a different language not related to the article.
3) Remove all links, emails, phone numbers, social handles, "click here" phrases, and shortened URLs.
4) Normalize markup and numbered/bulleted lists, fix numbering and spacing, remove duplicates.

# Publication Suitability Check
- The article is suitable if it is: informational/analytical/instructional with value, not purely promotional, free of hate speech/violence/explicit sexual content, and does not contain high-risk medical/financial advice without general explanatory context.
- Reject the article if it is: a low-value news snippet without details, an explicit advertisement, low-quality or recycled content with no added value, or violates the standards above.

# Preserve Structure
- Maintain the original structure (H2/H3 headings, paragraphs, lists, tables, quotes) but rewrite them in {$language}.{$rtlDirective}

# Length
- Preserve all essential information, explanations, and examples.
- Only shorten/remove noise and non-relevant elements (scraping artifacts).
- Do not pad unnecessarily, and do not drop any important information from the article body.

# Category Selection (category_id)
- Choose the category number closest to the article's topic ONLY from the provided list:
  {$categoriesJson}
- Do not use any ID not present in this list.

# Tags
- Create 3–5 precise tags in {$language} that reflect the article's main themes.

# Cover Image (text-to-image)
- Write a detailed, self-contained image description in English that captures the core idea, context, and visual elements; suitable for an image generation model that only understands English.

# Meta
- meta_title: an SEO-optimized title in {$language} (can equal the article title).
- meta_description: a concise {$language} description of 50–160 characters, clear and engaging.

# JSON Output (return ONLY a valid JSON object, no extra text):
{
  "status": "ok" or "reject",
  "reject_reasons": ["reason 1", "reason 2"],
  "title": "Creative {$language} title summarizing the idea",
  "text-to-image": "English, self-contained thumbnail description ...",
  "description": "2–3 sentence {$language} summary",
  "content": "<div>... full rewritten {$language} HTML ...</div>",
  "meta_title": "{$language} SEO title",
  "meta_description": "{$language} SEO description between 50-160 chars",
  "category_id": category_id_from_provided_list,
  "tags": ["tag 1", "tag 2", "tag 3"]
}

# Critical Rules
1) Translate and rewrite all essential parts of the article; do not drop any important information.
2) All output is in {$language} except "text-to-image" which is always in English.
3) Remove all links, emails, phone numbers, and click-bait calls.
4) Do not add opinions, sources, or website references; the article must stand on its own.
5) If the content is unsuitable for publication per the standards, return JSON with status="reject", fill reject_reasons briefly explaining why, and return the remaining fields gracefully (use short placeholders or leave empty as needed) so the JSON is still valid for import.
6) Use ONLY categories from the provided list.
7) Make sure the text does not read like a literal translation: rearrange structures and redistribute information without altering the meaning.

# Final Check (perform internally before outputting):
- [ ] All noise and links fully removed
- [ ] Style is fluent, clear, and original
- [ ] HTML structure is correct
- [ ] Length reflects all important information without filler
- [ ] meta_description is between 50–160 characters
- [ ] category_id is from the provided list
- [ ] Tags are precise and concise
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
