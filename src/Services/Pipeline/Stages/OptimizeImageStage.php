<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\ImageOptimizer;
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
        if (! $payload->imagePath) {
            return $next($payload);
        }

        try {
            $optimizedPath = $this->optimizer->optimizeExisting($payload->imagePath);

            Log::info('OptimizeImageStage: image optimized', [
                'original' => $payload->imagePath,
                'optimized' => $optimizedPath,
            ]);

            return $next($payload->with(['imagePath' => $optimizedPath]));
        } catch (\Throwable $e) {
            Log::warning('OptimizeImageStage: optimization failed, using original', [
                'error' => $e->getMessage(),
            ]);

            return $next($payload);
        }
    }
}
