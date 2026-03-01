<?php

namespace Badr\ScribeAi\Facades;

use Badr\ScribeAi\Services\Publishing\PublisherManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Badr\ScribeAi\Contracts\Publisher driver(?string $name = null)
 * @method static array publishToChannels(\Badr\ScribeAi\Models\Article $article, ?array $channels = null)
 * @method static static extend(string $driver, \Closure $callback)
 * @method static string[] availableDrivers()
 * @method static string getDefaultDriver()
 *
 * @see \Badr\ScribeAi\Services\Publishing\PublisherManager
 */
class Publisher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PublisherManager::class;
    }
}
