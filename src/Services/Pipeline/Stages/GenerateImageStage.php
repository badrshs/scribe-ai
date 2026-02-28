<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\Ai\ImageGenerator;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline stage: Generate a featured image using AI.
 *
 * Skipped if no image prompt was provided by the AI rewrite stage,
 * or if an image path is already set.
 */
class GenerateImageStage implements Pipe
{
    public function __construct(
        protected ImageGenerator $generator,
    ) {}

    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        if ($payload->imagePath) {
            Log::info('GenerateImageStage: skipped (image already present)');

            return $next($payload);
        }

        if (! $payload->imagePrompt) {
            Log::info('GenerateImageStage: skipped (no image prompt)');

            return $next($payload);
        }

        try {
            $imagePath = $this->generator->generate($payload->imagePrompt);

            Log::info('GenerateImageStage: image generated', ['path' => $imagePath]);

            return $next($payload->with(['imagePath' => $imagePath]));
        } catch (\Throwable $e) {
            Log::warning('GenerateImageStage: image generation failed, continuing without image', [
                'error' => $e->getMessage(),
            ]);

            return $next($payload);
        }
    }
}
