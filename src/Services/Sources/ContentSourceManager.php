<?php

namespace Bader\ContentPublisher\Services\Sources;

use Bader\ContentPublisher\Contracts\ContentSource;
use Bader\ContentPublisher\Services\Sources\Drivers\RssDriver;
use Bader\ContentPublisher\Services\Sources\Drivers\TextDriver;
use Bader\ContentPublisher\Services\Sources\Drivers\WebDriver;
use Closure;
use InvalidArgumentException;

/**
 * Manages content-source drivers (Strategy + Manager pattern).
 *
 * Mirrors PublisherManager but for the **input** side of the pipeline:
 * each driver knows how to fetch raw content from a particular medium.
 *
 * Register custom drivers via extend():
 *   app(ContentSourceManager::class)->extend('youtube', fn(array $config) => new YouTubeSource($config));
 *
 * Fetch content with auto-detection:
 *   app(ContentSourceManager::class)->fetch('https://example.com/article');
 *
 * Force a specific driver:
 *   app(ContentSourceManager::class)->driver('rss')->fetch('https://blog.com/feed.xml');
 */
class ContentSourceManager
{
    /** @var array<string, ContentSource> */
    protected array $resolved = [];

    /** @var array<string, Closure(array): ContentSource> */
    protected array $customCreators = [];

    /**
     * Resolve a content-source driver by name.
     *
     * When $name is null the default driver from config is used.
     */
    public function driver(?string $name = null): ContentSource
    {
        $name ??= $this->getDefaultDriver();

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Auto-detect the correct driver for the given identifier and fetch content.
     *
     * If $forcedDriver is set it takes priority over auto-detection.
     *
     * @return array{content: string, title?: string|null, meta?: array<string, mixed>}
     */
    public function fetch(string $identifier, ?string $forcedDriver = null): array
    {
        if ($forcedDriver) {
            return $this->driver($forcedDriver)->fetch($identifier);
        }

        // Auto-detect: iterate built-in + custom drivers, first match wins.
        foreach ($this->detectionOrder() as $name) {
            $driver = $this->driver($name);

            if ($driver->supports($identifier)) {
                return $driver->fetch($identifier);
            }
        }

        // Fallback to default driver when nothing matched.
        return $this->driver()->fetch($identifier);
    }

    /**
     * Register a custom content-source driver.
     *
     * @param  Closure(array): ContentSource  $callback
     */
    public function extend(string $name, Closure $callback): static
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    /**
     * Get all available driver names.
     *
     * @return string[]
     */
    public function availableDrivers(): array
    {
        $builtIn = array_keys(config('scribe-ai.sources.drivers', []));
        $custom = array_keys($this->customCreators);

        return array_unique(array_merge($builtIn, $custom));
    }

    /**
     * Get the default driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return config('scribe-ai.sources.default', 'web');
    }

    /**
     * Determine the driver detection order for auto-detection.
     *
     * More specific drivers (rss) are checked before general ones (web).
     * The text driver is always last because it matches any non-URL string.
     *
     * @return string[]
     */
    protected function detectionOrder(): array
    {
        // Specific → general. Custom drivers are checked after built-ins.
        $builtIn = ['rss', 'web', 'text'];
        $custom = array_keys($this->customCreators);

        // Insert custom drivers before the text fallback.
        $order = ['rss'];
        $order = array_merge($order, $custom);
        $order[] = 'web';
        $order[] = 'text';

        return array_unique($order);
    }

    /**
     * Resolve a driver instance by name.
     */
    protected function resolve(string $name): ContentSource
    {
        $config = config("scribe-ai.sources.drivers.{$name}", []);

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($config);
        }

        return match ($name) {
            'web' => new WebDriver($config),
            'rss' => new RssDriver($config),
            'text' => new TextDriver($config),
            default => throw new InvalidArgumentException(
                "Unsupported content source driver [{$name}]. Register it via ContentSourceManager::extend()."
            ),
        };
    }
}
