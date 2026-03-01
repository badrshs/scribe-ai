<?php

namespace Badr\ScribeAi\Facades;

use Badr\ScribeAi\Services\Sources\ContentSourceManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Badr\ScribeAi\Contracts\ContentSource driver(?string $name = null)
 * @method static array fetch(string $identifier, ?string $forcedDriver = null)
 * @method static static extend(string $name, \Closure $callback)
 * @method static string[] availableDrivers()
 * @method static string getDefaultDriver()
 *
 * @see \Badr\ScribeAi\Services\Sources\ContentSourceManager
 */
class ContentSource extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContentSourceManager::class;
    }
}
