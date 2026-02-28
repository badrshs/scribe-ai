<?php

namespace Bader\ContentPublisher\Facades;

use Bader\ContentPublisher\Services\Sources\ContentSourceManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bader\ContentPublisher\Contracts\ContentSource driver(?string $name = null)
 * @method static array fetch(string $identifier, ?string $forcedDriver = null)
 * @method static static extend(string $name, \Closure $callback)
 * @method static string[] availableDrivers()
 * @method static string getDefaultDriver()
 *
 * @see \Bader\ContentPublisher\Services\Sources\ContentSourceManager
 */
class ContentSource extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContentSourceManager::class;
    }
}
