<?php

namespace Badr\ScribeAi\Events;

use Badr\ScribeAi\Data\ContentPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the content pipeline finishes successfully.
 */
class PipelineCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly ?int $runId = null,
    ) {}
}
