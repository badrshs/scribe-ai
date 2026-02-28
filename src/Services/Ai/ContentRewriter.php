<?php

namespace Bader\ContentPublisher\Services\Ai;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Rewrites content using AI with configurable instructions.
 */
class ContentRewriter
{
    public function __construct(
        protected AiService $ai,
    ) {}

    /**
     * Rewrite content based on the given instruction.
     */
    public function rewrite(string $original, string $instruction, ?string $title = null): string
    {
        $prompt = $this->buildPrompt($original, $instruction, $title);

        $result = $this->ai->complete(
            systemPrompt: 'You are a professional content editor. Follow the instruction precisely. Output only the rewritten content, nothing else.',
            userPrompt: $prompt,
            maxTokens: (int) config('content-publisher.ai.max_tokens', 2000),
        );

        $this->validateOutput($result, $original, $instruction);

        Log::info('Content rewritten successfully', [
            'title' => $title,
            'original_length' => mb_strlen($original),
            'result_length' => mb_strlen($result),
        ]);

        return trim($result);
    }

    protected function buildPrompt(string $original, string $instruction, ?string $title): string
    {
        $parts = ["Instruction: {$instruction}"];

        if ($title) {
            $parts[] = "Title: {$title}";
        }

        $parts[] = "<original>\n{$original}\n</original>";

        return implode("\n\n", $parts);
    }

    /**
     * Reject suspiciously short output unless summarizing.
     */
    protected function validateOutput(string $result, string $original, string $instruction): void
    {
        $isSummarizing = str_contains(strtolower($instruction), 'summar');

        if (! $isSummarizing && mb_strlen($result) < mb_strlen($original) * 0.2) {
            throw new RuntimeException(
                'AI returned suspiciously short content: ' . mb_strlen($result) . ' chars vs original ' . mb_strlen($original) . ' chars'
            );
        }
    }
}
