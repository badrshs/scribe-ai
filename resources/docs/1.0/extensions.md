# Extension System

---

- [Overview](#overview)
- [How Extensions Work](#how-it-works)
- [Built-in Extensions](#built-in-extensions)
- [ExtensionManager](#extension-manager)
- [Registering Extensions](#registering)

<a name="overview"></a>
## Overview

Extensions are optional, self-contained modules that add complete workflows on top of the core pipeline. They follow the Extension contract and are managed by the `ExtensionManager`. Extensions are loaded lazily — `register()` and `boot()` are called only when `isEnabled()` returns true.

<a name="how-it-works"></a>
## How Extensions Work

The extension lifecycle follows Laravel's service provider pattern:

1. **Registration** — `ExtensionManager::register()` is called for each extension. If enabled, the extension's `register()` method binds services into the container.
2. **Booting** — `ExtensionManager::bootAll()` is called during the service provider boot phase. Enabled extensions register commands, routes, and listeners.

```
ServiceProvider::register()
  └── ExtensionManager::register(extension)
       └── extension->isEnabled() ? extension->register(app) : skip

ServiceProvider::boot()
  └── ExtensionManager::bootAll(app)
       └── for each enabled extension → extension->boot(app)
```

<a name="built-in-extensions"></a>
## Built-in Extensions

| Extension | Description | Config Key |
|-----------|-------------|------------|
| [Telegram Approval](/docs/1.0/extension-telegram-approval) | Human-in-the-loop approval via Telegram inline buttons | `extensions.telegram_approval.enabled` |

<a name="extension-manager"></a>
## ExtensionManager

The `ExtensionManager` is a singleton that tracks all registered extensions.

**Key methods:**

| Method | Description |
|--------|-------------|
| `register(Extension $ext, Application $app)` | Register an extension |
| `bootAll(Application $app)` | Boot all enabled extensions |
| `get(string $name)` | Get an extension by name |
| `all()` | Get all registered extensions |
| `enabled()` | Get only enabled extensions |
| `isEnabled(string $name)` | Check if an extension is active |

**Usage:**

```php
use Bader\ContentPublisher\Services\ExtensionManager;

$manager = app(ExtensionManager::class);

// Check if an extension is active
if ($manager->isEnabled('telegram-approval')) {
    // Extension-specific logic
}

// List all enabled extensions
$enabled = $manager->enabled();
```

<a name="registering"></a>
## Registering Extensions

### Via Config

Add custom extension class names to `config/scribe-ai.php`:

```php
'custom_extensions' => [
    App\Extensions\SlackApprovalExtension::class,
],
```

These are automatically registered by the `ContentPublisherServiceProvider`.

### Programmatically

In your service provider's `register()` method:

```php
use Bader\ContentPublisher\Services\ExtensionManager;

$manager = app(ExtensionManager::class);
$manager->register(new MyExtension(), $this->app);
```

See [Custom Extensions](/docs/1.0/extension-custom) for a full implementation guide.
