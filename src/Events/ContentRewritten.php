<?php

namespace Badr\ScribeAi\Events;

use Badr\ScribeAi\Data\ContentPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the AiRewriteStage successfully rewrites content.
 */
class ContentRewritten
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly string $title,
        public readonly ?int $categoryId = null,
    ) {}
}
