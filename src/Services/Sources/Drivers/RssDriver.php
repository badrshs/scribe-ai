<?php

namespace Bader\ContentPublisher\Services\Sources\Drivers;

use Bader\ContentPublisher\Contracts\ContentSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches content from an RSS or Atom feed URL.
 *
 * Parses the feed XML and returns the first (latest) entry by default.
 * The full list of entries is available in the `meta.entries` array.
 */
class RssDriver implements ContentSource
{
    public function __construct(
        protected array $config = [],
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fetch(string $identifier): array
    {
        $timeout = (int) ($this->config['timeout'] ?? 30);
        $maxItems = (int) ($this->config['max_items'] ?? 10);

        $response = Http::timeout($timeout)->get($identifier);

        if ($response->failed()) {
            throw new RuntimeException("RssDriver: failed to fetch feed [{$response->status()}]: {$identifier}");
        }

        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            throw new RuntimeException("RssDriver: invalid XML from {$identifier}");
        }

        $entries = $this->parseEntries($xml, $maxItems);

        if (empty($entries)) {
            throw new RuntimeException("RssDriver: no entries found in feed: {$identifier}");
        }

        $latest = $entries[0];

        Log::info('RssDriver: parsed feed', [
            'url' => $identifier,
            'total_entries' => count($entries),
            'latest_title' => $latest['title'] ?? null,
        ]);

        return [
            'content' => $latest['content'] ?? '',
            'title' => $latest['title'] ?? null,
            'meta' => [
                'source_driver' => $this->name(),
                'url' => $identifier,
                'entry_link' => $latest['link'] ?? null,
                'entry_date' => $latest['date'] ?? null,
                'entries' => $entries,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $identifier): bool
    {
        if (! filter_var($identifier, FILTER_VALIDATE_URL)) {
            return false;
        }

        $path = parse_url($identifier, PHP_URL_PATH) ?? '';

        // Match common feed URL patterns
        return (bool) preg_match('#(feed|rss|atom|\.xml|\.rss|\.atom)#i', $path);
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'rss';
    }

    /**
     * Parse RSS 2.0 or Atom entries from the feed XML.
     *
     * @return array<int, array{title: ?string, content: string, link: ?string, date: ?string}>
     */
    protected function parseEntries(\SimpleXMLElement $xml, int $maxItems): array
    {
        // RSS 2.0
        if (isset($xml->channel->item)) {
            return $this->parseRssItems($xml->channel->item, $maxItems);
        }

        // Atom
        $namespaces = $xml->getNamespaces(true);

        if (isset($xml->entry) || isset($namespaces[''])) {
            return $this->parseAtomEntries($xml, $maxItems);
        }

        return [];
    }

    /**
     * Parse RSS 2.0 <item> elements.
     *
     * @return array<int, array{title: ?string, content: string, link: ?string, date: ?string}>
     */
    protected function parseRssItems(\SimpleXMLElement $items, int $maxItems): array
    {
        $entries = [];
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $maxItems) {
                break;
            }

            $content = (string) ($item->children('content', true)->encoded ?? $item->description ?? '');

            $entries[] = [
                'title' => (string) ($item->title ?? ''),
                'content' => strip_tags($content, '<p><br><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><img>'),
                'link' => (string) ($item->link ?? ''),
                'date' => (string) ($item->pubDate ?? ''),
            ];

            $count++;
        }

        return $entries;
    }

    /**
     * Parse Atom <entry> elements.
     *
     * @return array<int, array{title: ?string, content: string, link: ?string, date: ?string}>
     */
    protected function parseAtomEntries(\SimpleXMLElement $xml, int $maxItems): array
    {
        $entries = [];
        $count = 0;

        foreach ($xml->entry as $entry) {
            if ($count >= $maxItems) {
                break;
            }

            $content = (string) ($entry->content ?? $entry->summary ?? '');
            $link = '';

            foreach ($entry->link as $entryLink) {
                $rel = (string) ($entryLink['rel'] ?? 'alternate');
                if ($rel === 'alternate') {
                    $link = (string) $entryLink['href'];

                    break;
                }
            }

            $entries[] = [
                'title' => (string) ($entry->title ?? ''),
                'content' => strip_tags($content, '<p><br><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><img>'),
                'link' => $link,
                'date' => (string) ($entry->updated ?? $entry->published ?? ''),
            ];

            $count++;
        }

        return $entries;
    }
}
