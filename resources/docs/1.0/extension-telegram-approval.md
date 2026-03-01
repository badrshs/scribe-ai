# Telegram Approval Extension

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Commands](#commands)
- [Webhook vs Polling](#webhook-vs-polling)
- [Webhook Setup](#webhook-setup)
- [Debugging](#debugging)
- [Full Workflow](#full-workflow)

<a name="overview"></a>
## Overview

The Telegram Approval extension adds a **human-in-the-loop** workflow: RSS feed entries are sent to a Telegram chat as cards with inline Approve and Reject buttons. When approved, the article is automatically dispatched through the content pipeline.

<a name="configuration"></a>
## Configuration

```dotenv
# Enable the extension
TELEGRAM_APPROVAL_ENABLED=true

# Bot credentials (can reuse the publishing bot or use a separate one)
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
TELEGRAM_CHAT_ID=-1001234567890

# Webhook (auto-resolved from APP_URL if not set)
TELEGRAM_WEBHOOK_URL=https://yourapp.com/api/scribe/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=your-secret-token
```

Config in `config/scribe-ai.php`:

```php
'extensions' => [
    'telegram_approval' => [
        'enabled'        => (bool) env('TELEGRAM_APPROVAL_ENABLED', false),
        'bot_token'      => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'        => env('TELEGRAM_CHAT_ID'),
        'webhook_url'    => env('TELEGRAM_WEBHOOK_URL'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_path'   => env('TELEGRAM_WEBHOOK_PATH', 'api/scribe/telegram/webhook'),
    ],
],
```

<a name="how-it-works"></a>
## How It Works

### 1. Fetch & Send

The `scribe:telegram-fetch-and-send` command:

1. Fetches entries from configured RSS feeds via the RSS driver
2. Filters out entries already in the `staged_contents` table
3. Saves new entries as `StagedContent` (unapproved)
4. Sends each entry to Telegram with inline keyboard buttons

### 2. Human Review

The reviewer sees a formatted message in Telegram:

```
New Article for Review

Title: 10 Tips for Better Productivity
Category: Technology

Summary: A comprehensive guide to...

Source: https://blog.com/article-123

[Approve]  [Reject]
```

### 3. Callback Processing

When a button is tapped:

- **Approve** - the full content pipeline runs automatically: the article is scraped, rewritten by AI, an image is generated, and the final article is published to all your configured channels (Telegram, Facebook, Blogger, WordPress, etc.)
- **Reject** - the entry is marked as rejected and discarded. **Nothing is published.** The entry will not appear again in future fetches.

> {primary} Only approved content gets published. Rejecting an entry permanently removes it from the queue.

<a name="commands"></a>
## Commands

### Fetch and Send

```bash
php artisan scribe:telegram-fetch-and-send
```

Fetches RSS entries and sends them for approval. Typically run on a schedule:

```php
// In your app's Console Kernel
$schedule->command('scribe:telegram-fetch-and-send')
    ->hourly()
    ->withoutOverlapping();
```

### Set Webhook

```bash
# Set the webhook
php artisan scribe:telegram-set-webhook

# Show current webhook status
php artisan scribe:telegram-set-webhook --info

# Remove the webhook
php artisan scribe:telegram-set-webhook --remove
```

The webhook URL is resolved in this order:
1. `TELEGRAM_WEBHOOK_URL` (explicit)
2. `APP_URL` + `TELEGRAM_WEBHOOK_PATH` (auto-resolved)

### Poll for Callbacks

```bash
# Continuous long-polling loop
php artisan scribe:telegram-poll

# Single pass - process pending callbacks and exit
php artisan scribe:telegram-poll --once

# Set timeout (default 30s)
php artisan scribe:telegram-poll --timeout=60

# Suppress console output
php artisan scribe:telegram-poll --silent
```

> {warning} Polling and webhooks are mutually exclusive. Calling `getUpdates` (used by the poll command) automatically disables any active webhook on the Telegram side. The poll command will warn you if a webhook is active.

<a name="webhook-vs-polling"></a>
## Webhook vs Polling

The extension supports two delivery modes for receiving button callbacks from Telegram:

| | Webhook | Polling |
|---|---|---|
| **How it works** | Telegram POSTs to your public URL | Your app calls Telegram's `getUpdates` API |
| **Best for** | Production, servers with public URLs | Local dev, firewalled environments |
| **Latency** | Instant | Depends on poll interval |
| **Requires** | Public URL (or ngrok for local dev) | Nothing - works anywhere |
| **Command** | `scribe:telegram-set-webhook` | `scribe:telegram-poll` |

Both modes use the same `CallbackHandler` internally, so the behavior is identical once a callback arrives.

> {primary} You must choose one mode at a time. Setting a webhook disables polling, and calling the poll command disables any active webhook.

<a name="webhook-setup"></a>
## Webhook Setup

The extension registers a webhook route at the path configured by `TELEGRAM_WEBHOOK_PATH` (default: `api/scribe/telegram/webhook`). The route is loaded automatically when the extension boots.

### Auto-webhook

The `TelegramApprovalService` automatically sets the webhook before sending the first approval message in each process. No manual setup required in most cases.

### Manual setup

Use the artisan command if you need to set, update, or reset the webhook:

```bash
php artisan scribe:telegram-set-webhook
```

### ngrok for local development

If you want to use webhooks during local development:

```bash
# 1. Start ngrok
ngrok http 8000

# 2. Set APP_URL or TELEGRAM_WEBHOOK_URL to your ngrok URL
TELEGRAM_WEBHOOK_URL=https://abc123.ngrok-free.app/api/scribe/telegram/webhook

# 3. Set the webhook
php artisan scribe:telegram-set-webhook

# 4. Verify it's registered
php artisan scribe:telegram-set-webhook --info
```

> {warning} Each time you restart ngrok, it generates a new URL (unless you have a paid plan with reserved domains). You must re-run `scribe:telegram-set-webhook` after restarting ngrok.

### Security

If `TELEGRAM_WEBHOOK_SECRET` is set, all incoming webhook requests are verified against the `X-Telegram-Bot-Api-Secret-Token` header. Requests with an invalid or missing token receive a 403 response.

<a name="debugging"></a>
## Debugging

### Check webhook status

```bash
php artisan scribe:telegram-set-webhook --info
```

This shows:
- Current webhook URL registered with Telegram
- Pending update count
- Last error date and message (if any)
- Whether the URL matches your config

### Common issues

**Buttons do nothing when clicked:**
1. Run `--info` to verify the webhook is registered with Telegram
2. Check that `APP_URL` or `TELEGRAM_WEBHOOK_URL` matches your public URL
3. If you ran `scribe:telegram-poll` at any point, it disabled your webhook - re-run `scribe:telegram-set-webhook`
4. Check Laravel logs for errors from `TelegramWebhook`

**Webhook was working but stopped:**
- Your ngrok URL may have changed (free plan generates new URLs on restart)
- Running `scribe:telegram-poll` even once disables the webhook
- Re-run `scribe:telegram-set-webhook` to fix

**Poll command returns nothing:**
- Ensure no webhook is active (`scribe:telegram-set-webhook --remove`)
- Check that the bot token and chat ID are correct

<a name="full-workflow"></a>
## Full Workflow

```
RSS Feed
  |
  +-- scribe:telegram-fetch-and-send
  |     +-- Fetch entries
  |     +-- Filter duplicates
  |     +-- Save to staged_contents
  |     +-- Send to Telegram
  |
  +-- Reviewer taps Approve
  |     +-- CallbackHandler processes
  |     +-- StagedContent marked approved
  |     +-- Telegram message updated
  |     +-- ProcessContentPipelineJob dispatched
  |
  +-- Pipeline runs
        +-- ScrapeStage (fetches full article)
        +-- AiRewriteStage
        +-- GenerateImageStage
        +-- OptimizeImageStage
        +-- CreateArticleStage
        +-- PublishStage -> channels
```

> {primary} The extension works seamlessly with the core pipeline - approval just controls **when** the pipeline runs.
