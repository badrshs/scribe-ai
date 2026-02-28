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

        Log::debug('AiService: raw JSON response', [
            'length' => mb_strlen($raw),
            'preview' => mb_substr($raw, 0, 300),
        ]);

        return $this->parseJson($raw);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendChatRequest(array $messages, string $model, int $maxTokens, bool $jsonMode): array
    {
        $tokenParam = $this->maxTokensParam($model);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            $tokenParam => $maxTokens,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        Log::info('AiService: sending chat request', [
            'model' => $model,
            'token_param' => $tokenParam,
            'max_tokens' => $maxTokens,
            'json_mode' => $jsonMode,
            'messages_count' => count($messages),
            'system_prompt_length' => mb_strlen($messages[0]['content'] ?? ''),
            'user_prompt_length' => mb_strlen($messages[1]['content'] ?? ''),
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('scribe-ai.ai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        Log::info('AiService: response received', [
            'model' => $model,
            'status' => $response->status(),
            'body_length' => mb_strlen($response->body()),
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "OpenAI API error [{$response->status()}]: " . $response->body()
            );
        }

        $json = $response->json();

        $finishReason = $json['choices'][0]['finish_reason'] ?? 'unknown';
        $usage = $json['usage'] ?? [];

        Log::info('AiService: completion details', [
            'model' => $model,
            'finish_reason' => $finishReason,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
        ]);

        if ($finishReason === 'length') {
            Log::warning('AiService: response was truncated (hit max tokens)', [
                'model' => $model,
                'max_tokens' => $maxTokens,
            ]);
        }

        return $json;
    }

    /**
     * Determine the correct max-tokens parameter name for the given model.
     *
     * Only truly new model families (gpt-5, o1, o3) require 'max_completion_tokens'.
     * The gpt-4o family supports both, so we keep using 'max_tokens' for compatibility.
     */
    protected function maxTokensParam(string $model): string
    {
        $requiresNew = preg_match('/^(gpt-5|o1|o3)/i', $model);

        return $requiresNew ? 'max_completion_tokens' : 'max_tokens';
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
