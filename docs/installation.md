# Installation

---

- [Composer Install](#composer-install)
- [Interactive Setup (Recommended)](#interactive-setup)
- [Manual Setup](#manual-setup)
- [Verification](#verification)

<a name="composer-install"></a>
## Composer Install

```bash
composer require badrshs/scribe-ai
```

<a name="interactive-setup"></a>
## Interactive Setup (Recommended)

The install wizard publishes config and migrations, asks for your AI provider & API keys, configures publish channels, and writes everything to `.env`:

```bash
php artisan scribe:install
```

The wizard will guide you through:

1. **AI Provider** — Choose between OpenAI, Claude, Gemini, or Ollama
2. **API Keys** — Securely enter your provider credentials
3. **Image Provider** — Optionally choose a separate image provider (OpenAI, Gemini, PiAPI)
4. **Content Model** — Set the AI model for content rewriting
5. **Output Language** — Choose the language for generated articles
6. **Publish Channels** — Select which platforms to publish to (log, Telegram, Facebook, Blogger, WordPress)
7. **Channel Credentials** — Enter tokens and IDs for selected channels
8. **Pipeline Settings** — Enable/disable run tracking, image optimization, Telegram approval

All values are written to your `.env` file. Existing values are updated; new ones are appended.

> {info} Use `--force` to overwrite existing config and migration files.

<a name="manual-setup"></a>
## Manual Setup

If you prefer manual configuration:

**1. Publish config and migrations:**

```bash
php artisan vendor:publish --tag=scribe-ai-config
php artisan vendor:publish --tag=scribe-ai-migrations
```

**2. Run migrations:**

```bash
php artisan migrate
```

**3. Add environment variables to `.env`:**

```env
# AI Provider
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...

# Publishing
PUBLISHER_CHANNELS=log
PUBLISHER_DEFAULT_CHANNEL=log
```

See [Configuration](/docs/1.0/configuration) for the full list of environment variables.

<a name="verification"></a>
## Verification

After installation, verify everything works with a quick test run:

```bash
php artisan scribe:process-url https://example.com/article --sync
```

You should see live progress output as each stage executes. The article will be published to the `log` channel by default (check `storage/logs/laravel.log`).
