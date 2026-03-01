# Quick Start

---

- [Minimal Setup](#minimal-setup)
- [Process Your First URL](#process-url)
- [Understanding the Output](#understanding-output)
- [Next Steps](#next-steps)

<a name="minimal-setup"></a>
## Minimal Setup

After [installation](/docs/1.0/installation), add your AI provider key to `.env`:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

That's the only required configuration. Everything else has sensible defaults.

<a name="process-url"></a>
## Process Your First URL

Run the pipeline on any URL:

```bash
php artisan scribe:process-url https://example.com/article --sync
```

You'll see real-time progress output:

```
  Scribe AI — Processing URL
  URL: https://example.com/article

  [1/6] Scrape  …
        ✓ completed — 4523 chars via web driver
  [2/6] AI Rewrite  …
        ✓ completed — "How Technology Is Reshaping Our Daily Lives"
  [3/6] Generate Image  …
        ✓ completed
  [4/6] Optimise Image  …
        ✓ completed
  [5/6] Create Article  …
        ✓ completed — ID #1
  [6/6] Publish  …
        ✓ completed — 1/1 channels succeeded

  ✅ Pipeline complete (3.42s)
  Article #1 — "How Technology Is Reshaping Our Daily Lives"
```

<a name="understanding-output"></a>
## Understanding the Output

Each stage reports its status:

| Icon | Meaning |
|------|---------|
| ✓ | Stage completed successfully |
| ⊘ | Stage was skipped (not needed or already done) |
| ✗ | Stage failed or content rejected |

The article is:
- Stored in the `articles` table
- Published to the `log` channel (check `storage/logs/laravel.log`)
- Images stored in `storage/app/public/articles/`

<a name="next-steps"></a>
## Next Steps

- **Use a different AI** → [AI Providers](/docs/1.0/ai-providers)
- **Publish to Telegram/Facebook** → [Publishing](/docs/1.0/publishing)
- **Process RSS feeds** → [Content Sources](/docs/1.0/content-sources)
- **Queue background processing** → Remove `--sync` to dispatch jobs
- **Listen to events** → [Event System](/docs/1.0/events)
- **Approve content before publishing** → [Telegram Approval](/docs/1.0/extension-telegram-approval)
