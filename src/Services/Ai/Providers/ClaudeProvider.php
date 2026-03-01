<?php

namespace Bader\ContentPublisher\Services\Ai\Providers;

use Bader\ContentPublisher\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Claude provider (Claude 3.5 Sonnet, Claude 3 Opus, etc.).
 *
 * Translates the OpenAI-style messages array to the Anthropic Messages API
 * format and normalizes the response back to the OpenAI schema the
 * package expects internally.
 */
class ClaudeProvider implements AiProvider
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? config('scribe-ai.ai.providers.claude.api_key', '');
        $this->baseUrl = $config['base_url'] ?? 'https://api.anthropic.com/v1';
    }

    public function name(): string
    {
        return 'claude';
    }

    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array
    {
        $systemPrompt = '';
        $apiMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt .= $msg['content'] . "\n";
            } else {
                $apiMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }
        }

        // When JSON mode is requested, instruct Claude to return only JSON
        if ($jsonMode) {
            $systemPrompt .= "\nIMPORTANT: You MUST respond with ONLY a valid JSON object. No text before or after.";
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $apiMessages,
        ];

        if ($systemPrompt) {
            $payload['system'] = trim($systemPrompt);
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post("{$this->baseUrl}/messages", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Claude API error [{$response->status()}]: " . $response->body()
            );
        }

        return $this->normalizeResponse($response->json());
    }

    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string
    {
        return null;
    }

    public function supportsImageGeneration(): bool
    {
        return false;
    }

    /**
     * Normalize Anthropic Messages API response to OpenAI-compatible format.
     */
    protected function normalizeResponse(array $data): array
    {
        $content = '';

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'];
            }
        }

        return [
            'choices' => [
                [
                    'message' => ['content' => $content, 'role' => 'assistant'],
                    'finish_reason' => $data['stop_reason'] ?? 'end_turn',
                ],
            ],
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? null,
                'completion_tokens' => $data['usage']['output_tokens'] ?? null,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }
}
