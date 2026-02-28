<?php

namespace Bader\ContentPublisher\Facades;

use Bader\ContentPublisher\Services\Publishing\PublisherManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Bader\ContentPublisher\Contracts\Publisher driver(?string $name = null)
 * @method static array publishToChannels(\Bader\ContentPublisher\Models\Article $article, ?array $channels = null)
 * @method static static extend(string $driver, \Closure $callback)
 * @method static string[] availableDrivers()
 * @method static string getDefaultDriver()
 *
 * @see \Bader\ContentPublisher\Services\Publishing\PublisherManager
 */
class Publisher extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PublisherManager::class;
    }
}
