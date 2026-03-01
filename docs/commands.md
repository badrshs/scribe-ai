# Artisan Commands

---

- [Overview](#overview)
- [scribe:install](#install)
- [scribe:process-url](#process-url)
- [scribe:publish](#publish)
- [scribe:publish-approved](#publish-approved)
- [scribe:resume](#resume)
- [scribe:telegram-set-webhook](#telegram-set-webhook)
- [scribe:telegram-fetch-and-send](#telegram-fetch-and-send)

<a name="overview"></a>
## Overview

Scribe AI registers several Artisan commands for managing the content pipeline, publishing, and extensions.

```bash
php artisan list scribe
```

<a name="install"></a>
## scribe:install

Interactive setup wizard that configures the package in a host application.

```bash
php artisan scribe:install
```

The wizard walks you through:

1. **AI Provider** — choose the default provider (OpenAI, Claude, Gemini, Ollama, PiAPI) and enter the API key.
2. **Image Provider** — select which provider generates featured images; only providers that support image generation are offered.
3. **Publish Channels** — pick one or more channels (Log, Telegram, Facebook, Blogger, WordPress) and configure their credentials.
4. **Pipeline Settings** — toggle optional stages and set queue names.

At the end it writes values to your `.env` file and publishes config/migrations automatically.

> {info} You can re-run `scribe:install` at any time to update settings. Existing values are preserved unless you change them.

<a name="process-url"></a>
## scribe:process-url

Feed a URL into the content pipeline.

```bash
php artisan scribe:process-url {url} [options]
```

| Option | Description |
|--------|-------------|
| `--sync` | Run synchronously instead of dispatching to the queue |
| `--silent` | Suppress console output |
| `--source={type}` | Force a content source driver (`web`, `rss`, `text`) |
| `--categories={list}` | Comma-separated `id:name` pairs (e.g. `1:Tech,2:Health`) |

**Examples:**

```bash
# Queue-based (default)
php artisan scribe:process-url https://example.com/article

# Synchronous with categories
php artisan scribe:process-url https://example.com/post --sync --categories="1:Tech,2:Science"

# Force RSS source
php artisan scribe:process-url https://blog.example.com/feed --source=rss
```

When run synchronously, each stage prints its status to the console with timing information.

<a name="publish"></a>
## scribe:publish

Publish an existing article by its database ID.

```bash
php artisan scribe:publish {articleId} [options]
```

| Option | Description |
|--------|-------------|
| `--channels={list}` | Comma-separated channel names to publish to (overrides configured channels) |

**Examples:**

```bash
# Publish to all configured channels
php artisan scribe:publish 42

# Publish to specific channels only
php artisan scribe:publish 42 --channels=telegram,facebook
```

Results are displayed as a table showing each channel's status and any error messages.

<a name="publish-approved"></a>
## scribe:publish-approved

Batch-publish staged content that has been approved (e.g., via the Telegram Approval extension).

```bash
php artisan scribe:publish-approved [options]
```

| Option | Description |
|--------|-------------|
| `--limit={n}` | Maximum number of articles to publish in this run (default: all pending) |

```bash
# Publish up to 5 approved articles
php artisan scribe:publish-approved --limit=5
```

> {primary} This command is useful in a scheduled task (e.g., Laravel's task scheduler) to periodically drain the approval queue.

<a name="resume"></a>
## scribe:resume

Resume a failed or interrupted pipeline run from its last completed stage.

```bash
php artisan scribe:resume {runId}
```

The command:

1. Loads the pipeline run and its saved payload state.
2. Identifies the last completed stage.
3. Resumes from the next stage with a progress display.
4. Updates the run status to **Completed** or **Failed**.

```bash
$ php artisan scribe:resume 7

 Resuming run #7 from GenerateImageStage...
 ✔ GenerateImageStage (1.2s)
 ✔ OptimizeImageStage (0.4s)
 ✔ CreateArticleStage (0.1s)
 ✔ PublishStage (2.3s)
 Pipeline completed successfully.
```

> {warning} Only runs with a **Failed** or **Pending** status can be resumed. Completed or Rejected runs will be refused.

<a name="telegram-set-webhook"></a>
## scribe:telegram-set-webhook

Register a Telegram webhook so the bot receives approval/rejection callbacks.

```bash
php artisan scribe:telegram-set-webhook
```

This sets the webhook URL to your application's callback route. The URL is built from `APP_URL` plus the route registered by the Telegram Approval extension.

> {info} The webhook is automatically registered when the Telegram Approval extension boots, so this command is mostly useful for re-registering after a URL change.

<a name="telegram-fetch-and-send"></a>
## scribe:telegram-fetch-and-send

Manually fetch pending staged content and send it to Telegram for approval.

```bash
php artisan scribe:telegram-fetch-and-send
```

This is an alternative to the automatic flow — useful for testing or when the webhook is down. It fetches all staged content with a `pending` status and sends each item to the configured Telegram chat with inline approve/reject buttons.
