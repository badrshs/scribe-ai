<?php

namespace Badr\ScribeAi\Facades;

use Badr\ScribeAi\Services\Pipeline\ContentPipeline as ContentPipelineService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Badr\ScribeAi\Data\ContentPayload process(\Badr\ScribeAi\Data\ContentPayload $payload)
 * @method static static through(array $stages)
 *
 * @see \Badr\ScribeAi\Services\Pipeline\ContentPipeline
 */
class ContentPipeline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContentPipelineService::class;
    }
}
