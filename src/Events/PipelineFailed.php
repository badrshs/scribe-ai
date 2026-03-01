<?php

namespace Bader\ContentPublisher\Events;

use Bader\ContentPublisher\Data\ContentPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the content pipeline fails (halts due to error or rejection).
 */
class PipelineFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly string $reason,
        public readonly ?string $stage = null,
        public readonly ?int $runId = null,
    ) {}
}
