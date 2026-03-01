# Overview

---

- [What is Scribe AI?](#what-is-scribe-ai)
- [Key Features](#key-features)
- [How It Works](#how-it-works)
- [Requirements](#requirements)

<a name="what-is-scribe-ai"></a>
## What is Scribe AI?

**Scribe AI** is a Laravel package that turns any URL into a published article — automatically. It scrapes a webpage, rewrites the content with AI, generates a cover image, optimises it for the web, saves the article to your database, and publishes it to one or more channels. One command. Zero manual steps.

```bash
php artisan scribe:process-url https://example.com/article --sync
```

> {primary} Built for **Laravel 11 & 12** · **PHP 8.2+** · **Queue-first** · **Fully extensible**

<a name="key-features"></a>
## Key Features

| Feature | Description |
|---------|-------------|
| **Automated Pipeline** | Six-stage content pipeline: Scrape → AI Rewrite → Image → Optimise → Save → Publish |
| **Multi-AI Providers** | OpenAI, Claude, Gemini, Ollama, PiAPI (Flux) — switch with one env var |
| **Multiple Publishers** | Telegram, Facebook, Blogger, WordPress, and a Log driver for development |
| **Content Sources** | Web scraping, RSS feeds, raw text — auto-detected or explicitly set |
| **Run Tracking** | Every pipeline execution is persisted and can be resumed from failure |
| **Event System** | Laravel events fired at every stage — hook into the content lifecycle |
| **Extension System** | Telegram approval workflow built-in; create your own extensions |
| **Image Optimization** | Auto-resize, compress, and convert to WebP |
| **Install Wizard** | Interactive `scribe:install` command configures everything |

<a name="how-it-works"></a>
## How It Works

Every URL passes through an ordered **pipeline** of stages. Each stage reads from an immutable `ContentPayload` DTO and passes a new copy to the next stage.

| # | Stage | What it does |
|---|-------|-------------|
| 1 | **ScrapeStage** | Extracts title, body, and metadata from the source URL |
| 2 | **AiRewriteStage** | Sends the raw content to AI and returns a polished article |
| 3 | **GenerateImageStage** | Creates a cover image with AI based on article context |
| 4 | **OptimizeImageStage** | Resizes, compresses, and converts the image to WebP |
| 5 | **CreateArticleStage** | Persists the article to the database with status, tags, and category |
| 6 | **PublishStage** | Pushes the article to every active publishing channel |

Stages are individually **skippable**, **replaceable**, and **reorderable** via config or at runtime.

```
ContentSourceManager → ContentPipeline → PublisherManager
   (input)              (processing)        (output)
```

<a name="requirements"></a>
## Requirements

- **PHP** 8.2 or higher
- **Laravel** 11 or 12
- At least one AI provider API key (OpenAI, Claude, Gemini, or a local Ollama instance)
- A database (SQLite, MySQL, PostgreSQL, SQL Server)
