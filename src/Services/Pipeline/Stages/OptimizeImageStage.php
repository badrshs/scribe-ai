<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\ImageOptimizer;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline stage: Optimize the generated image (resize, WebP conversion).
 *
 * Skipped if no image path exists on the payload.
 */
class OptimizeImageStage implements Pipe
{
    public function __construct(
        protected ImageOptimizer $optimizer,
    ) {}

    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        $pipeline = app(ContentPipeline::class);
        $pipeline->reportProgress('Optimise Image', 'started');

        if (! $payload->imagePath) {
            $pipeline->reportProgress('Optimise Image', 'skipped — no image to optimise');

            return $next($payload);
        }

        if (! config('scribe-ai.images.optimize', true)) {
            Log::info('OptimizeImageStage: optimization disabled via config');
            $pipeline->reportProgress('Optimise Image', 'skipped — disabled in config');

            return $next($payload);
        }

        try {
            $optimizedPath = $this->optimizer->optimizeExisting($payload->imagePath);

            Log::info('OptimizeImageStage: image optimized', [
                'original' => $payload->imagePath,
                'optimized' => $optimizedPath,
            ]);
            $pipeline->reportProgress('Optimise Image', 'completed');

            return $next($payload->with(['imagePath' => $optimizedPath]));
        } catch (\Throwable $e) {
            Log::warning('OptimizeImageStage: optimization failed', [
                'error' => $e->getMessage(),
            ]);
            $pipeline->reportProgress('Optimise Image', 'failed — ' . $e->getMessage());

            if (config('scribe-ai.pipeline.halt_on_error', true)) {
                return $payload->with([
                    'rejected' => true,
                    'rejectionReason' => 'Image optimization failed: ' . $e->getMessage(),
                ]);
            }

            return $next($payload);
        }
    }
}
