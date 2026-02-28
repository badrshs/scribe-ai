<?php

namespace Bader\ContentPublisher\Services\Pipeline\Stages;

use Bader\ContentPublisher\Contracts\Pipe;
use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Bader\ContentPublisher\Services\Sources\ContentSourceManager;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline stage: Fetch raw content from the source.
 *
 * Uses ContentSourceManager to auto-detect the right driver (web, rss, text)
 * or honours the explicit $payload->sourceDriver override.
 *
 * Skipped if rawContent is already present (e.g., provided manually).
 */
class ScrapeStage implements Pipe
{
    public function __construct(
        protected ContentSourceManager $sources,
    ) {}

    public function handle(ContentPayload $payload, Closure $next): mixed
    {
        $pipeline = app(ContentPipeline::class);
        $pipeline->reportProgress('Scrape', 'started');

        if ($payload->rawContent) {
            Log::info('ScrapeStage: skipped (content already present)');
            $pipeline->reportProgress('Scrape', 'skipped — content already present');

            return $next($payload);
        }

        if (! $payload->sourceUrl) {
            Log::warning('ScrapeStage: no source URL, skipping');
            $pipeline->reportProgress('Scrape', 'skipped — no source URL');

            return $next($payload);
        }

        $driverName = $payload->sourceDriver;
        $result = $this->sources->fetch($payload->sourceUrl, $driverName);

        $resolvedDriver = $result['meta']['source_driver'] ?? $driverName ?? 'auto';
        $contentLength = mb_strlen($result['content'] ?? '');

        Log::info('ScrapeStage: fetched content', [
            'url' => $payload->sourceUrl,
            'driver' => $resolvedDriver,
            'length' => $contentLength,
        ]);

        $overrides = [
            'rawContent' => $result['content'],
            'cleanedContent' => $result['content'],
        ];

        // Only set title from source if the payload doesn't already have one
        if (! $payload->title && ! empty($result['title'])) {
            $overrides['title'] = $result['title'];
        }

        // Merge source metadata into extra
        if (! empty($result['meta'])) {
            $overrides['extra'] = array_merge($payload->extra, ['source_meta' => $result['meta']]);
        }

        $pipeline->reportProgress('Scrape', "completed — {$contentLength} chars via {$resolvedDriver} driver");

        return $next($payload->with($overrides));
    }
}
