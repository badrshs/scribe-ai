# Telegram Approval Extension

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Commands](#commands)
- [Webhook Setup](#webhook-setup)
- [Full Workflow](#full-workflow)

<a name="overview"></a>
## Overview

The Telegram Approval extension adds a **human-in-the-loop** workflow: RSS feed entries are sent to a Telegram chat as cards with inline ✅ Approve and ❌ Reject buttons. When approved, the article is automatically dispatched through the content pipeline.

<a name="configuration"></a>
## Configuration

```dotenv
# Enable the extension
TELEGRAM_APPROVAL_ENABLED=true

# Bot credentials (can reuse the publishing bot or use a separate one)
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
TELEGRAM_CHAT_ID=-1001234567890

# Webhook (auto-resolved from APP_URL if not set)
TELEGRAM_WEBHOOK_URL=https://yourapp.com/api/telegram/webhook
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
📰 New Article for Review

Title: 10 Tips for Better Productivity
Category: Technology

Summary: A comprehensive guide to...

Source: https://blog.com/article-123

[✅ Approve]  [❌ Reject]
```

### 3. Callback Processing

When a button is tapped:

- **✅ Approve** → marks `StagedContent` as approved, dispatches `ProcessContentPipelineJob`, updates Telegram message
- **❌ Reject** → updates the Telegram message with rejection status

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

# Remove the webhook
php artisan scribe:telegram-set-webhook --remove
```

The webhook URL is auto-resolved from `APP_URL` if not explicitly configured.

<a name="webhook-setup"></a>
## Webhook Setup

The extension registers a webhook route at `/api/telegram/webhook` that receives callback queries from Telegram. The route is loaded automatically when the extension boots.

**Auto-webhook:** The `TelegramApprovalService` automatically ensures the webhook is set before sending the first approval message. No manual setup required in most cases.

**Manual setup:** Use the artisan command if you need to update or reset the webhook:

```bash
php artisan scribe:telegram-set-webhook
```

**Security:** If `TELEGRAM_WEBHOOK_SECRET` is set, all incoming webhook requests are verified against this secret token.

<a name="full-workflow"></a>
## Full Workflow

```
RSS Feed
  │
  ├── scribe:telegram-fetch-and-send
  │     ├── Fetch entries
  │     ├── Filter duplicates
  │     ├── Save to staged_contents
  │     └── Send to Telegram
  │
  ├── Reviewer taps ✅ Approve
  │     ├── CallbackHandler processes
  │     ├── StagedContent marked approved
  │     ├── Telegram message updated
  │     └── ProcessContentPipelineJob dispatched
  │
  └── Pipeline runs
        ├── ScrapeStage (fetches full article)
        ├── AiRewriteStage
        ├── GenerateImageStage
        ├── OptimizeImageStage
        ├── CreateArticleStage
        └── PublishStage → channels
```

> {primary} The extension works seamlessly with the core pipeline — approval just controls **when** the pipeline runs.
