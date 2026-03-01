<?php

namespace Badr\ScribeAi\Services\Ai\Providers;

use Badr\ScribeAi\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ollama provider for local/self-hosted models (Llama 3, Mistral, etc.).
 *
 * Ollama exposes an OpenAI-compatible API, so this provider is lightweight.
 * It defaults to http://localhost:11434 but can be pointed at any Ollama
 * instance via the host config.
 *
 * Note: Image generation is not supported via Ollama.
 */
class OllamaProvider implements AiProvider
{
    protected string $host;

    public function __construct(array $config = [])
    {
        $this->host = rtrim(
            $config['host'] ?? config('scribe-ai.ai.providers.ollama.host', 'http://localhost:11434'),
            '/'
        );
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'num_predict' => $maxTokens,
            ],
        ];

        if ($jsonMode) {
            $payload['format'] = 'json';
        }

        $response = Http::timeout(300)
            ->post("{$this->host}/api/chat", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Ollama API error [{$response->status()}]: " . $response->body()
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
     * Normalize Ollama response to OpenAI-compatible format.
     */
    protected function normalizeResponse(array $data): array
    {
        return [
            'choices' => [
                [
                    'message' => [
                        'content' => $data['message']['content'] ?? '',
                        'role' => $data['message']['role'] ?? 'assistant',
                    ],
                    'finish_reason' => $data['done'] ?? false ? 'stop' : 'length',
                ],
            ],
            'usage' => [
                'prompt_tokens' => $data['prompt_eval_count'] ?? null,
                'completion_tokens' => $data['eval_count'] ?? null,
                'total_tokens' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0),
            ],
        ];
    }
}
