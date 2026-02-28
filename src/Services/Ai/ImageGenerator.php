<?php

namespace Bader\ContentPublisher\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generates images using AI providers (OpenAI DALL-E, etc.).
 */
class ImageGenerator
{
    /**
     * Generate an image from a prompt and store it on disk.
     *
     * @return string Relative path to stored image
     */
    public function generate(string $prompt, ?string $model = null, ?string $size = null, ?string $quality = null): string
    {
        $model ??= config('scribe-ai.ai.image_model', 'dall-e-3');
        $size ??= config('scribe-ai.ai.image_size', '1024x1024');
        $quality ??= config('scribe-ai.ai.image_quality', 'standard');

        // Auto-correct size to a value the model actually supports.
        $size = $this->validateSize($model, $size);

        Log::info('Generating AI image', compact('model', 'size', 'quality'));

        $imageData = $this->requestImage($prompt, $model, $size, $quality);

        return $this->storeImage($imageData);
    }

    /**
     * @return string Raw image binary data
     */
    protected function requestImage(string $prompt, string $model, string $size, string $quality): string
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
            'Authorization' => 'Bearer ' . config('scribe-ai.ai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->post('https://api.openai.com/v1/images/generations', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Image generation API error [{$response->status()}]: " . $response->body()
            );
        }

        $data = $response->json('data.0');

        if (isset($data['b64_json'])) {
            return base64_decode($data['b64_json']);
        }

        if (isset($data['url'])) {
            return $this->downloadImage($data['url']);
        }

        throw new RuntimeException('No image data in API response');
    }

    protected function downloadImage(string $url): string
    {
        $response = Http::timeout(60)->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Failed to download generated image from: {$url}");
        }

        return $response->body();
    }

    protected function storeImage(string $imageData): string
    {
        $directory = config('scribe-ai.images.directory', 'articles');
        $disk = config('scribe-ai.images.disk', 'public');
        $filename = $directory . '/' . uniqid('ai-') . '.png';

        Storage::disk($disk)->put($filename, $imageData);

        Log::info('AI image stored', ['path' => $filename]);

        return $filename;
    }

    /**
     * Validate and auto-correct the image size for the given model.
     *
     * Each model family has a fixed set of supported sizes. If the
     * configured size is not valid, fall back to 1024x1024.
     */
    protected function validateSize(string $model, string $size): string
    {
        $supported = match (true) {
            str_starts_with($model, 'dall-e-2') => ['256x256', '512x512', '1024x1024'],
            str_starts_with($model, 'dall-e-3') => ['1024x1024', '1792x1024', '1024x1792'],
            // gpt-image-1 and future models
            default => ['1024x1024', '1024x1536', '1536x1024', 'auto'],
        };

        if (in_array($size, $supported, true)) {
            return $size;
        }

        Log::warning("Image size '{$size}' not supported by {$model}, falling back to 1024x1024", [
            'model' => $model,
            'requested' => $size,
            'supported' => $supported,
        ]);

        return '1024x1024';
    }
}
