<?php

namespace Badr\ScribeAi\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches and cleans HTML content from a given URL.
 *
 * Used by pipeline stages to scrape article content from source URLs.
 */
class WebScraper
{
    /**
     * Fetch the raw HTML from a URL.
     */
    public function fetch(string $url): string
    {
        $timeout = (int) config('scribe-ai.sources.drivers.web.timeout', 30);
        $userAgent = config('scribe-ai.sources.drivers.web.user_agent', 'Mozilla/5.0 (compatible; ContentBot/1.0)');

        $response = Http::withHeaders([
            'User-Agent' => $userAgent,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ])
            ->timeout($timeout)
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch URL [{$response->status()}]: {$url}");
        }

        Log::info('Web scraper fetched URL', [
            'url' => $url,
            'status' => $response->status(),
            'size' => strlen($response->body()),
        ]);

        return $response->body();
    }

    /**
     * Fetch and clean HTML, removing non-content elements.
     */
    public function scrape(string $url): string
    {
        $html = $this->fetch($url);

        return $this->clean($html);
    }

    /**
     * Strip scripts, styles, nav, footer, and other non-content elements.
     */
    public function clean(string $html): string
    {
        $patterns = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<nav\b[^>]*>.*?<\/nav>/is',
            '/<footer\b[^>]*>.*?<\/footer>/is',
            '/<header\b[^>]*>.*?<\/header>/is',
            '/<aside\b[^>]*>.*?<\/aside>/is',
            '/<form\b[^>]*>.*?<\/form>/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<!--.*?-->/s',
        ];

        $cleaned = preg_replace($patterns, '', $html);
        $text = strip_tags($cleaned, '<p><br><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><a><img>');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
