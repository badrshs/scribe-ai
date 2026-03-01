<?php

namespace Bader\ContentPublisher\Services\Ai\Providers;

use Bader\ContentPublisher\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PiAPI provider (piapi.ai) — Flux image generation.
 *
 * PiAPI is an image-only provider using the Flux model.
 * It does NOT support text/chat completions.
 *
 * Config: scribe-ai.ai.providers.piapi
 * Env:    PIAPI_API_KEY, PIAPI_BASE_URL
 *
 * Usage:
 *   AI_IMAGE_PROVIDER=piapi
 *   PIAPI_API_KEY=your-key
 */
class PiApiProvider implements AiProvider
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(protected array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.piapi.ai', '/');
    }

    /**
     * Chat is not supported — PiAPI is image-only.
     */
    public function chat(array $messages, string $model, int $maxTokens = 4096, bool $jsonMode = false): array
    {
        throw new RuntimeException(
            "PiAPI provider does not support chat/text completions. Use a different provider for AI_PROVIDER."
        );
    }

    /**
     * Generate an image using Flux via PiAPI.
     *
     * @return string|null Raw image binary data
     */
    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string
    {
        if (! $this->apiKey) {
            throw new RuntimeException('PiAPI API key is not configured. Set PIAPI_API_KEY in your .env.');
        }

        [$width, $height] = $this->parseSize($size);

        // Create the generation task
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/api/flux/v1/run", [
            'model' => $model ?: 'flux-1',
            'task_type' => 'txt2img',
            'input' => [
                'prompt' => $prompt,
                'width' => $width,
                'height' => $height,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "PiAPI API error [{$response->status()}]: " . $response->body()
            );
        }

        $data = $response->json();
        $taskId = $data['data']['task_id'] ?? null;

        if (! $taskId) {
            throw new RuntimeException('PiAPI did not return a task_id: ' . $response->body());
        }

        // Poll for completion
        return $this->pollForResult($taskId);
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'piapi';
    }

    /**
     * Poll the task endpoint until the image is ready.
     */
    protected function pollForResult(string $taskId): ?string
    {
        $maxAttempts = (int) ($this->config['poll_max_attempts'] ?? 30);
        $intervalMs = (int) ($this->config['poll_interval_ms'] ?? 3000);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            usleep($intervalMs * 1000);

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
            ])->get("{$this->baseUrl}/api/flux/v1/task/{$taskId}");

            if ($response->failed()) {
                continue;
            }

            $data = $response->json();
            $status = $data['data']['status'] ?? '';

            if ($status === 'completed') {
                $imageUrl = $data['data']['output']['image_url']
                    ?? $data['data']['output']['images'][0]['url']
                    ?? null;

                if (! $imageUrl) {
                    throw new RuntimeException('PiAPI completed but returned no image URL');
                }

                return Http::get($imageUrl)->body();
            }

            if ($status === 'failed') {
                $error = $data['data']['error'] ?? 'Unknown error';

                throw new RuntimeException("PiAPI image generation failed: {$error}");
            }
        }

        throw new RuntimeException("PiAPI image generation timed out after {$maxAttempts} poll attempts");
    }

    /**
     * Parse a "WxH" size string into [width, height].
     *
     * @return array{0: int, 1: int}
     */
    protected function parseSize(string $size): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [1024, 1024];
    }
}
