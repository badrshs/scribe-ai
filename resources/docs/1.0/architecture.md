# Architecture

---

- [System Overview](#system-overview)
- [Three-Layer Design](#three-layer-design)
- [Key Classes](#key-classes)
- [Data Flow](#data-flow)
- [Design Patterns](#design-patterns)

<a name="system-overview"></a>
## System Overview

Scribe AI is structured as three independent layers connected by immutable DTOs:

```
┌──────────────────────────────────────────────────────┐
│                 ContentSourceManager                  │
│                                                       │
│  identifier → auto-detect / forced driver             │
│  driver('web')  → WebDriver::fetch()                  │
│  driver('rss')  → RssDriver::fetch()                  │
│  driver('text') → TextDriver::fetch()                 │
├──────────────────────────────────────────────────────┤
│                    ContentPipeline                     │
│                                                       │
│  ContentPayload → Stage 1 → Stage 2 → ... → Stage N  │
│       (DTO)       Scrape    Rewrite       Publish     │
│                                                       │
│  AiProviderManager provides AI (text & image)         │
│  Each stage tracked in PipelineRun (DB)               │
│  Failed? → snapshot saved → resume from that stage    │
│  Events dispatched after each stage                   │
├──────────────────────────────────────────────────────┤
│                   PublisherManager                     │
│                                                       │
│  driver('facebook') → FacebookDriver::publish()       │
│  driver('telegram') → TelegramDriver::publish()       │
│                                                       │
│  Each result → PublishResult DTO → publish_logs table  │
└──────────────────────────────────────────────────────┘
```

<a name="three-layer-design"></a>
## Three-Layer Design

| Layer | Class | Role |
|-------|-------|------|
| **Input** | `ContentSourceManager` | Resolves content-source drivers (web, rss, text, custom). Auto-detects which driver to use from the identifier. |
| **Processing** | `ContentPipeline` | Runs stages in sequence through an immutable `ContentPayload` DTO. Tracks each step in a `PipelineRun`. |
| **Output** | `PublisherManager` | Resolves publish drivers (log, telegram, facebook, etc.) and dispatches article to all active channels. |

Supporting the pipeline:

| Class | Role |
|-------|------|
| `AiProviderManager` | Resolves AI backends (openai, claude, gemini, ollama, piapi). Separate text & image providers. |
| `ExtensionManager` | Loads optional extensions (e.g., Telegram approval) only when enabled. |

<a name="key-classes"></a>
## Key Classes

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `ContentPayload` | `Data\` | Immutable DTO carrying state between stages. `with()` creates new instances. Supports `toSnapshot()` / `fromSnapshot()`. |
| `PublishResult` | `Data\` | Per-channel outcome DTO. Auto-persisted to `publish_logs`. |
| `ContentPipeline` | `Services\Pipeline\` | Orchestrates stage execution, manages progress callbacks, dispatches events. |
| `PipelineRun` | `Models\` | Eloquent model tracking run state, snapshots, and errors in `pipeline_runs`. |
| `AiProviderManager` | `Services\Ai\` | Driver manager for AI providers with `extend()` for custom providers. |
| `AiService` | `Services\Ai\` | High-level AI service used by stages (chat, completeJson). Delegates to the active provider. |
| `ImageGenerator` | `Services\Ai\` | AI image generation + disk storage. Uses the image-specific provider. |
| `PublisherManager` | `Services\Publishing\` | Channel driver manager with `extend()` for custom publishers. |
| `ContentSourceManager` | `Services\Sources\` | Input driver manager with auto-detection and `extend()`. |
| `ExtensionManager` | `Services\` | Registers and boots optional extension modules. |

<a name="data-flow"></a>
## Data Flow

```
URL / Text
    ↓
ContentSourceManager::fetch()
    ↓
ContentPayload (rawContent set)
    ↓
ScrapeStage → ContentScraped event
    ↓
AiRewriteStage → ContentRewritten event
    ↓
GenerateImageStage → ImageGenerated event
    ↓
OptimizeImageStage → ImageOptimized event
    ↓
CreateArticleStage → ArticleCreated event  →  Article model
    ↓
PublishStage → ArticlePublished event(s)  →  PublishResult(s)
    ↓
ContentPayload (final state returned)
PipelineCompleted event
```

<a name="design-patterns"></a>
## Design Patterns

| Pattern | Where |
|---------|-------|
| **Pipeline** | `ContentPipeline` sends a payload through an ordered list of stages (Laravel's `Illuminate\Pipeline\Pipeline` under the hood). |
| **Strategy + Manager** | `PublisherManager`, `ContentSourceManager`, `AiProviderManager` all use the Manager pattern — resolve named drivers with `driver()` / `provider()` and allow `extend()`. |
| **Immutable DTO** | `ContentPayload` has `readonly` properties. `with()` returns a new instance — stages never mutate the original. |
| **Event-Driven** | Every stage dispatches a Laravel event. Pipeline-level events for start/complete/fail. |
| **Extension** | `ExtensionManager` loads optional modules that implement `Contracts\Extension`. Disabled extensions have zero overhead. |
