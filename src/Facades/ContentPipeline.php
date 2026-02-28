<?php

namespace Bader\ContentPublisher\Facades;

use Bader\ContentPublisher\Services\Pipeline\ContentPipeline as ContentPipelineService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bader\ContentPublisher\Data\ContentPayload process(\Bader\ContentPublisher\Data\ContentPayload $payload)
 * @method static static through(array $stages)
 *
 * @see \Bader\ContentPublisher\Services\Pipeline\ContentPipeline
 */
class ContentPipeline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContentPipelineService::class;
    }
}
