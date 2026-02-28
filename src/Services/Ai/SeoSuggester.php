<?php

namespace Bader\ContentPublisher\Services\Ai;

use Illuminate\Support\Str;

/**
 * Generates SEO-optimized suggestions for articles using AI.
 */
class SeoSuggester
{
    public function __construct(
        protected AiService $ai,
    ) {}

    /**
     * Generate SEO suggestions for the given content.
     *
     * @return array{title: string, meta_title: string, meta_description: string, slug: string, description: string}
     */
    public function suggest(string $title, string $content = ''): array
    {
        $prompt = "Generate SEO-optimized metadata for an article.\n\n"
            . "Title: {$title}\n";

        if ($content) {
            $prompt .= 'Content preview: ' . Str::limit($content, 500) . "\n";
        }

        $prompt .= "\nReturn a JSON object with these exact keys:\n"
            . '- "title": optimized title (max 70 chars)' . "\n"
            . '- "meta_title": SEO meta title (max 60 chars)' . "\n"
            . '- "meta_description": compelling meta description (max 160 chars)' . "\n"
            . '- "slug": URL-friendly slug' . "\n"
            . '- "description": short article description (max 180 chars)' . "\n";

        $result = $this->ai->completeJson(
            systemPrompt: 'You are an SEO specialist. Return only the requested JSON object.',
            userPrompt: $prompt,
            maxTokens: 300,
        );

        return $this->normalize($result);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{title: string, meta_title: string, meta_description: string, slug: string, description: string}
     */
    protected function normalize(array $raw): array
    {
        return [
            'title' => Str::limit($raw['title'] ?? '', 70, ''),
            'meta_title' => Str::limit($raw['meta_title'] ?? '', 60, ''),
            'meta_description' => Str::limit($raw['meta_description'] ?? '', 160, ''),
            'slug' => Str::slug($raw['slug'] ?? ''),
            'description' => Str::limit($raw['description'] ?? '', 180, ''),
        ];
    }
}
