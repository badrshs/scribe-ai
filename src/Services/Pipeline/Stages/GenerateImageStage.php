<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Events\ImageGenerated;
use Bader\ContentPublisher\Services\Ai\ImageGenerator;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
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
        $pipeline = app(ContentPipeline::class);
        $pipeline->reportProgress('Generate Image', 'started');

        if ($payload->imagePath) {
            Log::info('GenerateImageStage: skipped (image already present)');
            $pipeline->reportProgress('Generate Image', 'skipped — image already present');

            return $next($payload);
        }

        if (! $payload->imagePrompt) {
            Log::info('GenerateImageStage: skipped (no image prompt)');
            $pipeline->reportProgress('Generate Image', 'skipped — no image prompt');

            return $next($payload);
        }

        try {
            $imagePath = $this->generator->generate($payload->imagePrompt);

            Log::info('GenerateImageStage: image generated', ['path' => $imagePath]);
            $pipeline->reportProgress('Generate Image', 'completed');

            $newPayload = $payload->with(['imagePath' => $imagePath]);

            event(new ImageGenerated($newPayload, $imagePath));

            return $next($newPayload);
        } catch (\Throwable $e) {
            Log::warning('GenerateImageStage: image generation failed', [
                'error' => $e->getMessage(),
            ]);
            $pipeline->reportProgress('Generate Image', 'failed — ' . $e->getMessage());

            if (config('scribe-ai.pipeline.halt_on_error', true)) {
                return $payload->with([
                    'rejected' => true,
                    'rejectionReason' => 'Image generation failed: ' . $e->getMessage(),
                ]);
            }

            return $next($payload);
        }
    }
}
