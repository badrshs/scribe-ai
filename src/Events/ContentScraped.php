<?php

namespace Bader\ContentPublisher\Events;

use Bader\ContentPublisher\Data\ContentPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the ScrapeStage successfully fetches content.
 */
class ContentScraped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly string $driver,
        public readonly int $contentLength,
    ) {}
}
