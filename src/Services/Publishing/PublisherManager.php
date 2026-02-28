<?php

namespace Bader\ContentPublisher\Services\Publishing;

use Bader\ContentPublisher\Contracts\Publisher;
use Bader\ContentPublisher\Data\PublishResult;
use Bader\ContentPublisher\Models\Article;
use Bader\ContentPublisher\Models\PublishLog;
use Bader\ContentPublisher\Services\Publishing\Drivers\BloggerDriver;
use Bader\ContentPublisher\Services\Publishing\Drivers\FacebookDriver;
use Bader\ContentPublisher\Services\Publishing\Drivers\LogDriver;
use Bader\ContentPublisher\Services\Publishing\Drivers\TelegramDriver;
use Bader\ContentPublisher\Services\Publishing\Drivers\WordPressDriver;
use Closure;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Manages publisher drivers (Strategy pattern + Manager pattern).
 *
 * Register custom drivers via extend():
 *   app(PublisherManager::class)->extend('custom', fn(array $config) => new MyPublisher($config));
 *
 * Publish to a single channel:
 *   app(PublisherManager::class)->driver('facebook')->publish($article);
 *
 * Publish to all configured channels:
 *   app(PublisherManager::class)->publishToChannels($article);
 */
class PublisherManager
{
    /** @var array<string, Publisher> */
    protected array $resolved = [];

    /** @var array<string, Closure(array): Publisher> */
    protected array $customCreators = [];

    /**
     * Resolve a publisher driver by name.
     */
    public function driver(?string $name = null): Publisher
    {
        $name ??= $this->getDefaultDriver();

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Publish an article to all active channels and log results.
     *
     * @param  string[]|null  $channels  Override active channels
     * @return array<string, PublishResult>
     */
    public function publishToChannels(Article $article, ?array $channels = null): array
    {
        $channels ??= config('scribe-ai.channels', ['log']);
        $results = [];

        foreach ($channels as $channel) {
            try {
                $driver = $this->driver($channel);

                if (! $driver->supports($article)) {
                    Log::info("Publisher [{$channel}] does not support this article, skipping", [
                        'article_id' => $article->id,
                    ]);

                    continue;
                }

                if ($article->wasPublishedTo($channel)) {
                    Log::info("Article already published to [{$channel}], skipping", [
                        'article_id' => $article->id,
                    ]);

                    continue;
                }

                $result = $driver->publish($article);
                $results[$channel] = $result;

                $this->logResult($article, $result);
            } catch (\Throwable $e) {
                $result = PublishResult::failure($channel, $e->getMessage());
                $results[$channel] = $result;

                $this->logResult($article, $result);

                Log::error("Publishing to [{$channel}] failed", [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Register a custom publisher driver.
     *
     * @param  Closure(array): Publisher  $callback
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all available driver names.
     *
     * @return string[]
     */
    public function availableDrivers(): array
    {
        $configured = array_keys(config('scribe-ai.drivers', []));
        $custom = array_keys($this->customCreators);

        return array_unique(array_merge($configured, $custom));
    }

    public function getDefaultDriver(): string
    {
        return config('scribe-ai.default', 'log');
    }

    protected function resolve(string $name): Publisher
    {
        $config = config("scribe-ai.drivers.{$name}", []);

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($config);
        }

        $driverType = $config['driver'] ?? $name;

        return match ($driverType) {
            'log' => new LogDriver($config),
            'facebook' => new FacebookDriver($config),
            'telegram' => new TelegramDriver($config),
            'blogger' => new BloggerDriver($config),
            'wordpress' => new WordPressDriver($config),
            default => throw new InvalidArgumentException("Unsupported publisher driver [{$driverType}]. Register it via extend()."),
        };
    }

    protected function logResult(Article $article, PublishResult $result): void
    {
        if ($result->success) {
            PublishLog::logSuccess(
                $article->id,
                $result->channel,
                $result->externalId,
                $result->externalUrl,
                $result->metadata,
            );
        } else {
            PublishLog::logFailure(
                $article->id,
                $result->channel,
                $result->error ?? 'Unknown error',
                $result->metadata,
            );
        }
    }
}
