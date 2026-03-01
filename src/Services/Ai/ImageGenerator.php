<?php

namespace Bader\ContentPublisher\Services\Ai;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generates images using the configured AI provider.
 *
 * Delegates to the AiProviderManager to resolve the image-capable provider,
 * allowing OpenAI DALL-E, Gemini Imagen, or any custom provider.
 */
class ImageGenerator
{
    public function __construct(
        protected AiProviderManager $providerManager,
    ) {}

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

        $provider = $this->providerManager->imageProvider();

        if (! $provider->supportsImageGeneration()) {
            throw new RuntimeException(
                "AI provider '{$provider->name()}' does not support image generation. "
                    . "Set AI_IMAGE_PROVIDER to a provider that does (e.g. openai, gemini)."
            );
        }

        Log::info('Generating AI image', [
            'provider' => $provider->name(),
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
        ]);

        $imageData = $provider->generateImage($prompt, $model, $size, $quality);

        if (! $imageData) {
            throw new RuntimeException("Provider '{$provider->name()}' returned no image data");
        }

        return $this->storeImage($imageData);
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
     */
    protected function validateSize(string $model, string $size): string
    {
        $supported = match (true) {
            str_starts_with($model, 'dall-e-2') => ['256x256', '512x512', '1024x1024'],
            str_starts_with($model, 'dall-e-3') => ['1024x1024', '1792x1024', '1024x1792'],
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
