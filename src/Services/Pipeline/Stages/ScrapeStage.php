<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\WebScraper;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline stage: Scrape raw content from the source URL.
 *
 * Skipped if rawContent is already present (e.g., provided manually).
 */
class ScrapeStage implements Pipe
{
    public function __construct(
        protected WebScraper $scraper,
    ) {}

    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        if ($payload->rawContent) {
            Log::info('ScrapeStage: skipped (content already present)');

            return $next($payload);
        }

        if (! $payload->sourceUrl) {
            Log::warning('ScrapeStage: no source URL, skipping');

            return $next($payload);
        }

        $rawContent = $this->scraper->scrape($payload->sourceUrl);

        Log::info('ScrapeStage: scraped content', [
            'url' => $payload->sourceUrl,
            'length' => mb_strlen($rawContent),
        ]);

        return $next($payload->with([
            'rawContent' => $rawContent,
            'cleanedContent' => $rawContent,
        ]));
    }
}
