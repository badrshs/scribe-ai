<?php

namespace Badr\ScribeAi\Extensions\TelegramApproval;

use Badr\ScribeAi\Models\StagedContent;
use Badr\ScribeAi\Services\Ai\AiService;
use Badr\ScribeAi\Services\Sources\ContentSourceManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fetch an RSS feed, optionally filter by AI, and send each entry
 * to Telegram for human approval.
 *
 * Phase 1 of the Telegram Approval workflow:
 *   RSS → AI analysis → Telegram messages with ✅/❌ → StagedContent (pending)
 *
 * Phase 2 is handled by TelegramPollCommand or the webhook controller.
 */
class RssReviewCommand extends Command
{
    protected $signature = 'scribe:rss-review
        {url : The RSS/Atom feed URL to fetch}
        {--days=7 : Only include entries from the last N days}
        {--ai-filter : Use AI to rank and summarise each entry}
        {--limit=10 : Maximum entries to send for review}
        {--silent : Suppress console output}';

    protected $description = 'Fetch an RSS feed and send entries to Telegram for approval';

    public function handle(
        ContentSourceManager $sources,
        TelegramApprovalService $telegram,
        AiService $ai,
    ): int {
        $feedUrl = $this->argument('url');
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $useAi = (bool) $this->option('ai-filter');
        $silent = (bool) $this->option('silent');

        if (! $silent) {
            $this->components->info('Scribe AI — RSS Review');
            $this->line("  <comment>Feed:</comment>  {$feedUrl}");
            $this->line("  <comment>Days:</comment>  {$days}    <comment>Limit:</comment> {$limit}    <comment>AI:</comment> " . ($useAi ? 'yes' : 'no'));
            $this->newLine();
        }

        // ── 1. Fetch RSS entries via the rss driver ──────────────────
        try {
            $result = $sources->driver('rss')->fetch($feedUrl);
        } catch (\Throwable $e) {
            if (! $silent) {
                $this->components->error("Failed to fetch feed: {$e->getMessage()}");
            }

            return self::FAILURE;
        }

        $entries = $result['meta']['entries'] ?? [];

        if (empty($entries)) {
            if (! $silent) {
                $this->components->warn('No entries found in the feed.');
            }

            return self::SUCCESS;
        }

        if (! $silent) {
            $this->line("  <fg=gray>Found " . count($entries) . " entries in feed</>");
        }

        // ── 2. Filter by date ────────────────────────────────────────
        $cutoff = Carbon::now()->subDays($days);

        $entries = array_filter($entries, function (array $entry) use ($cutoff) {
            if (empty($entry['date'])) {
                return true; // include entries without dates
            }

            try {
                return Carbon::parse($entry['date'])->gte($cutoff);
            } catch (\Throwable) {
                return true;
            }
        });

        $entries = array_values($entries);

        if (empty($entries)) {
            if (! $silent) {
                $this->components->warn("No entries within the last {$days} days.");
            }

            return self::SUCCESS;
        }

        // ── 3. Deduplicate against existing staged content ───────────
        $urls = array_column($entries, 'link');
        $existing = StagedContent::query()
            ->whereIn('url', array_filter($urls))
            ->pluck('url')
            ->toArray();

        $entries = array_values(array_filter(
            $entries,
            fn(array $e) => ! empty($e['link']) && ! in_array($e['link'], $existing),
        ));

        if (empty($entries)) {
            if (! $silent) {
                $this->components->info('All entries already staged — nothing new.');
            }

            return self::SUCCESS;
        }

        // Apply limit
        $entries = array_slice($entries, 0, $limit);

        if (! $silent) {
            $this->line("  <fg=gray>" . count($entries) . " new entries to review</>");
            $this->newLine();
        }

        // ── 4. Optional AI analysis ──────────────────────────────────
        if ($useAi) {
            $entries = $this->aiAnalyse($ai, $entries, $silent);
        }

        // ── 5. Store as StagedContent and send to Telegram ───────────
        $sent = 0;

        foreach ($entries as $entry) {
            $staged = StagedContent::create([
                'title' => $entry['title'] ?? 'Untitled',
                'url' => $entry['link'],
                'published_date' => $this->parseDate($entry['date'] ?? null),
                'category' => $entry['ai_category'] ?? null,
                'source_name' => parse_url($feedUrl, PHP_URL_HOST) ?? $feedUrl,
            ]);

            try {
                $telegram->sendForApproval(
                    stagedContentId: $staged->id,
                    title: $staged->title,
                    url: $staged->url,
                    summary: $entry['ai_summary'] ?? null,
                    category: $staged->category,
                );

                $sent++;

                if (! $silent) {
                    $this->line("  <fg=green>✓</> Sent: <options=bold>{$staged->title}</>");
                }
            } catch (\Throwable $e) {
                Log::error('TelegramApproval: failed to send message', [
                    'staged_content_id' => $staged->id,
                    'error' => $e->getMessage(),
                ]);

                if (! $silent) {
                    $this->line("  <fg=red>✗</> Failed: {$staged->title} — {$e->getMessage()}");
                }
            }

            // Small delay to avoid hitting Telegram rate limits
            usleep(300_000);
        }

        if (! $silent) {
            $this->newLine();
            $this->components->info("Sent {$sent} entries to Telegram for review.");
            $this->line('  <fg=gray>Approve or reject them in Telegram, then run <comment>scribe:telegram-poll</comment> to process decisions.</>');
        }

        return self::SUCCESS;
    }

    /**
     * Use AI to summarise and categorise each entry.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    protected function aiAnalyse(AiService $ai, array $entries, bool $silent): array
    {
        if (! $silent) {
            $this->line('  <fg=cyan>Analysing entries with AI…</>');
        }

        $titles = array_map(fn(array $e, int $i) => ($i + 1) . ". {$e['title']}", $entries, array_keys($entries));

        $systemPrompt = <<<'PROMPT'
You are a content curation assistant. You will receive a numbered list of article titles.
For each article, respond with a JSON array of objects having these keys:
- "index" (1-based, matching the input numbering)
- "relevant" (boolean — is this article worth publishing?)
- "summary" (1-2 sentence summary based only on the title)
- "category" (a single-word topic category, e.g. "Technology", "Health", "Business")

Return ONLY the JSON array, no markdown fences.
PROMPT;

        $userPrompt = implode("\n", $titles);

        try {
            $result = $ai->completeJson($systemPrompt, $userPrompt, maxTokens: 2048);

            // Ensure it's a flat array (not nested under a key)
            $items = isset($result[0]) ? $result : ($result['articles'] ?? $result['entries'] ?? []);

            foreach ($items as $item) {
                $idx = (int) ($item['index'] ?? 0) - 1;

                if (! isset($entries[$idx])) {
                    continue;
                }

                $entries[$idx]['ai_summary'] = $item['summary'] ?? null;
                $entries[$idx]['ai_category'] = $item['category'] ?? null;
                $entries[$idx]['ai_relevant'] = $item['relevant'] ?? true;
            }

            // Filter out entries AI marked as irrelevant
            $before = count($entries);
            $entries = array_values(array_filter($entries, fn(array $e) => $e['ai_relevant'] ?? true));
            $filtered = $before - count($entries);

            if (! $silent && $filtered > 0) {
                $this->line("  <fg=yellow>⊘ AI filtered out {$filtered} irrelevant entries</>");
            }
        } catch (\Throwable $e) {
            Log::warning('TelegramApproval: AI analysis failed, continuing without', [
                'error' => $e->getMessage(),
            ]);

            if (! $silent) {
                $this->line("  <fg=yellow>⚠ AI analysis failed, sending all entries</>");
            }
        }

        return $entries;
    }

    protected function parseDate(?string $date): ?\Carbon\Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
