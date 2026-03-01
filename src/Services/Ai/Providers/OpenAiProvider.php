<?php

namespace Badr\ScribeAi\Services\Ai\Providers;

use Badr\ScribeAi\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI provider (GPT-4o, GPT-4o-mini, DALL-E, gpt-image-1, etc.).
 */
class OpenAiProvider implements AiProvider
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? config('scribe-ai.ai.api_key', '');
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1';
    }

    public function name(): string
    {
        return 'openai';
    }

    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array
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

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "OpenAI API error [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json();
    }

    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string
    {
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality,
        ];

        if (in_array($model, ['dall-e-2', 'dall-e-3'])) {
            $payload['response_format'] = 'b64_json';
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post("{$this->baseUrl}/images/generations", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "OpenAI image API error [{$response->status()}]: " . $response->body()
            );
        }

        $data = $response->json('data.0');

        if (isset($data['b64_json'])) {
            return base64_decode($data['b64_json']);
        }

        if (isset($data['url'])) {
            $imgResponse = Http::timeout(60)->get($data['url']);

            if ($imgResponse->failed()) {
                throw new RuntimeException("Failed to download generated image from: {$data['url']}");
            }

            return $imgResponse->body();
        }

        throw new RuntimeException('No image data in OpenAI response');
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    protected function maxTokensParam(string $model): string
    {
        return preg_match('/^(gpt-5|o1|o3)/i', $model)
            ? 'max_completion_tokens'
            : 'max_tokens';
    }
}
