<?php

namespace Badr\ScribeAi\Events;

use Badr\ScribeAi\Data\ContentPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the GenerateImageStage creates an image.
 */
class ImageGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPayload $payload,
        public readonly string $imagePath,
    ) {}
}
