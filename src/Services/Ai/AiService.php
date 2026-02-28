<?php

namespace Bader\ContentPublisher\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Core wrapper around the OpenAI API with model fallback support.
 *
 * All AI services in the package delegate to this class for
 * the actual API calls. It handles retries, model fallback, and
 * response normalization.
 */
class AiService
{
    /**
     * Send a chat completion request with automatic fallback.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?string $model = null, int $maxTokens = 0, bool $jsonMode = false): array
    {
        $primaryModel = $model ?? config('scribe-ai.ai.content_model', 'gpt-4o-mini');
        $fallbackModel = config('scribe-ai.ai.fallback_model', 'gpt-4o-mini');
        $maxTokens = $maxTokens ?: (int) config('scribe-ai.ai.max_tokens', 2000);

        try {
            return $this->sendChatRequest($messages, $primaryModel, $maxTokens, $jsonMode);
        } catch (RuntimeException $e) {
            if ($primaryModel === $fallbackModel) {
                throw $e;
            }

            Log::warning('AI primary model failed, falling back', [
                'primary' => $primaryModel,
                'fallback' => $fallbackModel,
                'error' => $e->getMessage(),
            ]);

            return $this->sendChatRequest($messages, $fallbackModel, $maxTokens, $jsonMode);
        }
    }

    /**
     * Convenience method: get a plain text completion.
     */
    public function complete(string $systemPrompt, string $userPrompt, ?string $model = null, int $maxTokens = 0): string
    {
        $response = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $model, $maxTokens);

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Convenience method: get a JSON-parsed completion.
     *
     * @return array<string, mixed>
     */
    public function completeJson(string $systemPrompt, string $userPrompt, ?string $model = null, int $maxTokens = 0): array
    {
        $response = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $model, $maxTokens, jsonMode: true);

        $raw = $response['choices'][0]['message']['content'] ?? '{}';

        return $this->parseJson($raw);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendChatRequest(array $messages, string $model, int $maxTokens, bool $jsonMode): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('scribe-ai.ai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "OpenAI API error [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Parse JSON from AI response, stripping code fences if present.
     *
     * @return array<string, mixed>
     */
    protected function parseJson(string $raw): array
    {
        $cleaned = $raw;
        if (preg_match('/```(?:json)?\s*\n(.+?)\n\s*```/is', $raw, $matches)) {
            $cleaned = $matches[1];
        }

        $decoded = json_decode(trim($cleaned), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse AI JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
