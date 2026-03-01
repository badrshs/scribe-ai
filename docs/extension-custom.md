# Custom Extensions

---

- [Overview](#overview)
- [The Extension Contract](#contract)
- [Implementing an Extension](#implementing)
- [Registering the Extension](#registering)
- [Full Example](#full-example)

<a name="overview"></a>
## Overview

Build your own extensions to add complete workflows on top of the Scribe AI pipeline. Extensions can register services, commands, routes, event listeners, and anything else a Laravel service provider can do.

<a name="contract"></a>
## The Extension Contract

```php
namespace Badr\ScribeAi\Contracts;

use Illuminate\Contracts\Foundation\Application;

interface Extension
{
    /** Unique identifier (e.g. 'slack-approval'). */
    public function name(): string;

    /** Whether the extension is currently enabled. */
    public function isEnabled(): bool;

    /** Register bindings (called during register phase). */
    public function register(Application $app): void;

    /** Boot the extension: commands, routes, listeners (called during boot phase). */
    public function boot(Application $app): void;
}
```

<a name="implementing"></a>
## Implementing an Extension

**Example: Slack approval extension**

```php
<?php

namespace App\Extensions;

use Badr\ScribeAi\Contracts\Extension;
use Illuminate\Contracts\Foundation\Application;

class SlackApprovalExtension implements Extension
{
    public function name(): string
    {
        return 'slack-approval';
    }

    public function isEnabled(): bool
    {
        return (bool) config('scribe-ai.extensions.slack_approval.enabled', false);
    }

    public function register(Application $app): void
    {
        // Register services
        $app->singleton(SlackApprovalService::class, function () {
            return new SlackApprovalService(
                config('scribe-ai.extensions.slack_approval')
            );
        });
    }

    public function boot(Application $app): void
    {
        // Register commands
        if ($app->runningInConsole()) {
            $commands = [
                SlackFetchCommand::class,
                SlackPollCommand::class,
            ];

            // Register with Artisan
            foreach ($commands as $command) {
                $app->make(\Illuminate\Contracts\Console\Kernel::class);
            }
        }

        // Register routes
        $app->make('router')->middleware('api')->group(function ($router) {
            $router->post('/api/slack/interact', [SlackInteractController::class, 'handle']);
        });

        // Register event listeners
        \Illuminate\Support\Facades\Event::listen(
            \Badr\ScribeAi\Events\ArticleCreated::class,
            function ($event) {
                app(SlackApprovalService::class)->notifyChannel(
                    "New article created: {$event->article->title}"
                );
            }
        );
    }
}
```

<a name="registering"></a>
## Registering the Extension

### Option 1: Via Config (Recommended)

Add your class to `config/scribe-ai.php`:

```php
'custom_extensions' => [
    \App\Extensions\SlackApprovalExtension::class,
],
```

### Option 2: Programmatically

In your service provider:

```php
use Badr\ScribeAi\Services\ExtensionManager;

public function register(): void
{
    $manager = app(ExtensionManager::class);
    $manager->register(new \App\Extensions\SlackApprovalExtension(), $this->app);
}
```

### Add Config

Add your extension's config block:

```php
'extensions' => [
    'slack_approval' => [
        'enabled'     => (bool) env('SLACK_APPROVAL_ENABLED', false),
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
        'channel'     => env('SLACK_APPROVAL_CHANNEL', '#content-review'),
    ],
],
```

<a name="full-example"></a>
## Full Example

A minimal extension that logs pipeline events to a Slack channel:

```php
class SlackNotifierExtension implements Extension
{
    public function name(): string { return 'slack-notifier'; }

    public function isEnabled(): bool
    {
        return !empty(config('scribe-ai.extensions.slack_notifier.webhook_url'));
    }

    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Event::listen(PipelineCompleted::class, function ($event) {
            Http::post(config('scribe-ai.extensions.slack_notifier.webhook_url'), [
                'text' => "✅ Pipeline completed: {$event->payload->title}",
            ]);
        });

        Event::listen(PipelineFailed::class, function ($event) {
            Http::post(config('scribe-ai.extensions.slack_notifier.webhook_url'), [
                'text' => "❌ Pipeline failed: {$event->reason}",
            ]);
        });
    }
}
```

> {primary} Extensions follow the enable/disable pattern. Always check `isEnabled()` via a config or env toggle so users can activate extensions without code changes.
