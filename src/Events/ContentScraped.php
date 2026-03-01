<?php

namespace Badr\ScribeAi\Events;

use Badr\ScribeAi\Data\ContentPayload;
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
