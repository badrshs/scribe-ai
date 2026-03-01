<?php

namespace Bader\ContentPublisher\Events;

use Bader\ContentPublisher\Data\ContentPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the content pipeline starts processing a payload.
 */
class PipelineStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly ?int $runId = null,
    ) {}
}
