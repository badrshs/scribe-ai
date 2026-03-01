# Scribe AI — Copilot Instructions

## Package identity
- **Composer name**: `badrshs/scribe-ai` | **PHP namespace**: `Bader\ContentPublisher`
- Laravel package (no standalone entrypoint). All classes are registered as singletons in `ContentPublisherServiceProvider`.
- Targets Laravel 11/12 and PHP 8.2+.

## Testing Workflow - The "done" Command Loop Veru crocial

Before completing ANY task, you MUST:

1. Run `php artisan done` command and wait for approval
2. user will test, check your code and potentially modify the command with the next instructions
3. If user modifies the command, address their feedback
4. Then Run `php .\artisan done` again
5. Repeat until the command runs successfully without user modifications

## Core architecture

### 1. Content Pipeline
`ContentPipeline` sends an immutable `ContentPayload` DTO through an ordered list of stages using Laravel's `Illuminate\Pipeline\Pipeline`. Default stage order (configured in `config/scribe-ai.php` → `pipeline.stages`):

```
ScrapeStage → AiRewriteStage → GenerateImageStage → OptimizeImageStage → CreateArticleStage → PublishStage
```

**Writing a stage** — implement `Contracts\Pipe`:
```php
public function handle(ContentPayload $payload, Closure $next): mixed
{
    // Transform and continue:
    return $next($payload->with(['title' => $newTitle]));
    // Or reject (halt pipeline without calling $next):
    return $payload->with(['rejected' => true, 'rejectionReason' => 'duplicate']);
}
```

**Mutating the payload** — `ContentPayload` has `readonly` properties. Always use `$payload->with([...])` to produce a new instance; never try to assign properties directly.

**One-off custom stage order**:
```php
app(ContentPipeline::class)->through([ScrapeStage::class, MyStage::class])->process($payload);
```
`customStages` is consumed and cleared after a single `process()` call.

### 2. Publisher Manager
`PublisherManager` is a Strategy + Manager pattern. Built-in drivers: `log`, `facebook`, `telegram`, `blogger`, `wordpress`.

**Adding a custom driver** (in a service provider):
```php
app(PublisherManager::class)->extend('medium', fn(array $config) => new MediumDriver($config));
```

The driver must implement `Contracts\Publisher` (three methods: `publish`, `supports`, `channel`).

Active channels are resolved from `PUBLISHER_CHANNELS` (comma-separated env var). Use `log` driver during development — it never makes external calls.

### 3. Data flow
`ContentPayload` carries state between stages (URL → scraped content → AI-rewritten article → image path → saved `Article` model → publish results). `PublishResult` is the per-channel outcome; results are auto-persisted to `publish_logs` via `PublisherManager::logResult()`.

## Developer workflows

```bash
# Run the full pipeline for a URL (queued by default):
php artisan scribe:process-url https://example.com/article
# Run synchronously (no queue):
php artisan scribe:process-url https://example.com/article --sync

# Publish a specific article (by ID) to configured channels:
php artisan scribe:publish 1
php artisan scribe:publish 1 --channels=facebook,telegram

# Drain staged content that has been approved:
php artisan scribe:publish-approved --limit=5

# Tag and publish a new package release:
.\release.ps1   # or release.bat on cmd
```

Publish config/migrations after installing:
```bash
php artisan vendor:publish --tag=scribe-ai-config
php artisan vendor:publish --tag=scribe-ai-migrations
php artisan migrate
```

## Conventions
- **Stage skipping**: A stage should silently skip (call `$next($payload)` unchanged) when its required input is already present (e.g., `ScrapeStage` skips if `rawContent` is set) or missing (logs a warning, then continues).
- **Logging**: Every stage logs its action via `Log::info()`/`Log::warning()` with `source_url` and relevant IDs — keep this consistent in new stages.
- **Config keys**: All package config lives under `scribe-ai.*`. Driver-specific config is at `scribe-ai.drivers.<name>`.
- **Facades**: `Publisher` → `PublisherManager`, `ContentPipeline` → `ContentPipeline` service. Prefer resolving from the container (`app(PublisherManager::class)`) inside service providers and jobs.
- **Jobs**: `ProcessContentPipelineJob` queues a full pipeline run; `PublishArticleJob` queues a single-article publish. Both are dispatchable directly.

## Key files
| Purpose | File |
|---|---|
| Service wiring | `src/ContentPublisherServiceProvider.php` |
| Pipeline orchestration | `src/Services/Pipeline/ContentPipeline.php` |
| Stage contract | `src/Contracts/Pipe.php` |
| Shared payload DTO | `src/Data/ContentPayload.php` |
| Publisher manager | `src/Services/Publishing/PublisherManager.php` |
| Publisher contract | `src/Contracts/Publisher.php` |
| Reference stage | `src/Services/Pipeline/Stages/ScrapeStage.php` |
| Reference driver | `src/Services/Publishing/Drivers/LogDriver.php` |
| Package config | `config/scribe-ai.php` |
| AI Provider contract | `src/Contracts/AiProvider.php` |
| AI Provider manager | `src/Services/Ai/AiProviderManager.php` |
| Extension manager | `src/Services/Extensions/ExtensionManager.php` |
| Content source manager | `src/Services/Sources/ContentSourceManager.php` |
| Events | `src/Events/*.php` |
| Documentation (LaRecipe) | `resources/docs/1.0/` |

## Documentation (`resources/docs/1.0/`)

The project uses [LaRecipe](https://larecipe.saleem.dev) for documentation. All docs live in `resources/docs/1.0/` as Markdown files with LaRecipe conventions (`<a name="section"></a>` anchors, `> {primary}` / `> {info}` / `> {warning}` callouts).

**When making code changes, always update the corresponding documentation page(s).** The sidebar navigation is defined in `resources/docs/1.0/index.md`. Current doc pages:

| Section | Pages |
|---------|-------|
| Getting Started | `overview`, `installation`, `quick-start`, `configuration` |
| Core Concepts | `architecture`, `pipeline`, `stages`, `payload`, `run-tracking` |
| AI Providers | `ai-providers`, `provider-openai`, `provider-claude`, `provider-gemini`, `provider-ollama`, `provider-piapi`, `provider-custom` |
| Content Sources | `content-sources`, `source-web`, `source-rss`, `source-text`, `source-custom` |
| Publishing | `publishing`, `driver-log`, `driver-telegram`, `driver-facebook`, `driver-blogger`, `driver-wordpress`, `driver-custom` |
| Events | `events`, `events-pipeline`, `events-stages` |
| Extensions | `extensions`, `extension-telegram-approval`, `extension-custom` |
| Advanced | `categories`, `image-optimization`, `queues`, `commands`, `testing` |

**Rules:**
- If you add a new stage, driver, provider, event, or extension — create a new doc page and add it to `index.md`.
- If you change config keys, update `configuration.md` and any affected pages.
- If you add or modify an Artisan command, update `commands.md`.
- Keep code examples in docs in sync with actual source code.
