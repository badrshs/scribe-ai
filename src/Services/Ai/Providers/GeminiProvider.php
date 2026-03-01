<?php

namespace Badr\ScribeAi\Services\Ai\Providers;

use Badr\ScribeAi\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini provider (gemini-2.0-flash, gemini-1.5-pro, etc.).
 *
 * Uses the Gemini API's generateContent endpoint and normalizes the
 * response to the OpenAI-compatible schema the package uses internally.
 */
class GeminiProvider implements AiProvider
{
    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? config('scribe-ai.ai.providers.gemini.api_key', '');
        $this->baseUrl = $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array
    {
        $systemInstruction = '';
        $contents = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction .= $msg['content'] . "\n";
            } else {
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        if ($jsonMode) {
            $systemInstruction .= "\nIMPORTANT: Respond with ONLY a valid JSON object. No markdown fences, no extra text.";
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => trim($systemInstruction)]],
            ];
        }

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Gemini API error [{$response->status()}]: " . $response->body()
            );
        }

        return $this->normalizeResponse($response->json());
    }

    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string
    {
        // Gemini Imagen — uses the same generateContent endpoint with image generation mode
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Gemini image API error [{$response->status()}]: " . $response->body()
            );
        }

        $data = $response->json();

        // Extract inline image data from response
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['inlineData']['data'])) {
                return base64_decode($part['inlineData']['data']);
            }
        }

        throw new RuntimeException('No image data in Gemini response');
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    /**
     * Normalize Gemini API response to OpenAI-compatible format.
     */
    protected function normalizeResponse(array $data): array
    {
        $candidate = $data['candidates'][0] ?? [];
        $content = '';

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            $content .= $part['text'] ?? '';
        }

        $usage = $data['usageMetadata'] ?? [];

        return [
            'choices' => [
                [
                    'message' => ['content' => $content, 'role' => 'assistant'],
                    'finish_reason' => strtolower($candidate['finishReason'] ?? 'stop'),
                ],
            ],
            'usage' => [
                'prompt_tokens' => $usage['promptTokenCount'] ?? null,
                'completion_tokens' => $usage['candidatesTokenCount'] ?? null,
                'total_tokens' => $usage['totalTokenCount'] ?? null,
            ],
        ];
    }
}
