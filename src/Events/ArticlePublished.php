<?php

namespace Bader\ContentPublisher\Events;

use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Data\PublishResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after each channel publish attempt (success or failure).
 */
class ArticlePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly PublishResult $result,
        public readonly string $channel,
    ) {}
}
