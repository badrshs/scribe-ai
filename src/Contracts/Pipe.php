<?php

namespace Bader\ContentPublisher\Contracts;

use Bader\ContentPublisher\Data\ContentPayload;
use Closure;

interface Pipe
{
    /**
     * Process the content payload and pass it to the next stage.
     *
     * Return $next($payload) to continue the pipeline.
     * Return $payload directly to halt (e.g., on rejection).
     */
    public function handle(ContentPayload $payload, Closure $next): mixed;
}
