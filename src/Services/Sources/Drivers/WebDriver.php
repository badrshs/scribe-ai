<?php

namespace Bader\ContentPublisher\Services\Sources\Drivers;

use Bader\ContentPublisher\Contracts\ContentSource;
use Bader\ContentPublisher\Services\WebScraper;
use Illuminate\Support\Facades\Log;

/**
 * Fetches content by scraping a web page.
 *
 * Wraps the existing WebScraper service, converting its output into
 * the structured array the pipeline expects.
 */
class WebDriver implements ContentSource
{
    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fetch(string $identifier): array
    {
        $scraper = app(WebScraper::class);

        $content = $scraper->scrape($identifier);

        Log::info('WebDriver: fetched content', [
            'url' => $identifier,
            'length' => mb_strlen($content),
        ]);

        return [
            'content' => $content,
            'title' => null,
            'meta' => [
                'source_driver' => $this->name(),
                'url' => $identifier,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_URL) !== false
            && preg_match('#\.(xml|rss|atom)$#i', parse_url($identifier, PHP_URL_PATH) ?? '') === 0;
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'web';
    }
}
