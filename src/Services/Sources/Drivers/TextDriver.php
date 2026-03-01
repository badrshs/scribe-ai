<?php

namespace Badr\ScribeAi\Services\Sources\Drivers;

use Badr\ScribeAi\Contracts\ContentSource;
use Illuminate\Support\Facades\Log;

/**
 * Accepts raw text or markdown content directly — no network call.
 *
 * Useful for testing, bulk imports, or when the content has already
 * been fetched externally and you just need to push it through the
 * AI rewriting pipeline.
 */
class TextDriver implements ContentSource
{
    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fetch(string $identifier): array
    {
        Log::info('TextDriver: accepted raw content', [
            'length' => mb_strlen($identifier),
        ]);

        return [
            'content' => $identifier,
            'title' => null,
            'meta' => [
                'source_driver' => $this->name(),
            ],
        ];
    }

    /**
     * The text driver supports identifiers that are NOT valid URLs.
     *
     * {@inheritDoc}
     */
    public function supports(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_URL) === false;
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'text';
    }
}
