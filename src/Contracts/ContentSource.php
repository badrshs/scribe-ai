<?php

namespace Bader\ContentPublisher\Contracts;

/**
 * Contract for content source drivers.
 *
 * Each driver knows how to fetch raw content from a specific type of
 * input (web page, RSS feed, raw text, etc.) and return it as a
 * structured array that the pipeline can consume.
 *
 * Register custom drivers via ContentSourceManager::extend():
 *   app(ContentSourceManager::class)->extend('youtube', fn($config) => new YouTubeSource($config));
 */
interface ContentSource
{
    /**
     * Fetch and return content from the given identifier.
     *
     * The identifier is driver-specific: a URL for web/rss drivers,
     * raw text for the text driver, a file path for file-based drivers, etc.
     *
     * @return array{content: string, title?: string|null, meta?: array<string, mixed>}
     */
    public function fetch(string $identifier): array;

    /**
     * Determine if this driver can handle the given identifier.
     *
     * Used by auto-detection: the manager iterates drivers and picks the
     * first one that returns true.
     */
    public function supports(string $identifier): bool;

    /**
     * Get the unique name of this source driver.
     */
    public function name(): string;
}
