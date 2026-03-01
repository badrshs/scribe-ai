<?php

namespace Badr\ScribeAi\Services\Pipeline\Stages;

use Badr\ScribeAi\Contracts\Pipe;
use Badr\ScribeAi\Data\ContentPayload;
use Badr\ScribeAi\Events\ImageOptimized;
use Badr\ScribeAi\Services\ImageOptimizer;
use Badr\ScribeAi\Services\Pipeline\ContentPipeline;
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

            $originalPath = $payload->imagePath;

            Log::info('OptimizeImageStage: image optimized', [
                'original' => $originalPath,
                'optimized' => $optimizedPath,
            ]);
            $pipeline->reportProgress('Optimise Image', 'completed');

            $newPayload = $payload->with(['imagePath' => $optimizedPath]);

            event(new ImageOptimized($newPayload, $originalPath, $optimizedPath));

            return $next($newPayload);
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
