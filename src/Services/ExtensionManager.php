<?php

namespace Badr\ScribeAi\Services;

use Badr\ScribeAi\Contracts\Extension;
use Illuminate\Contracts\Foundation\Application;

/**
 * Registry for Scribe AI extensions.
 *
 * Extensions are self-contained modules (e.g. Telegram Approval,
 * Slack Review, etc.) that sit on top of the core pipeline.
 *
 * Usage in a service provider:
 *
 *     $manager = app(ExtensionManager::class);
 *     $manager->register(new TelegramApprovalExtension());
 *
 * The manager calls register() and boot() on each enabled extension
 * at the appropriate lifecycle phase.
 */
class ExtensionManager
{
    /** @var array<string, Extension> */
    protected array $extensions = [];

    /** @var array<string, bool> */
    protected array $booted = [];

    /**
     * Register an extension with the manager.
     *
     * If the extension is enabled, its register() method is called immediately.
     */
    public function register(Extension $extension, Application $app): void
    {
        $this->extensions[$extension->name()] = $extension;

        if ($extension->isEnabled()) {
            $extension->register($app);
        }
    }

    /**
     * Boot all registered and enabled extensions.
     */
    public function bootAll(Application $app): void
    {
        foreach ($this->extensions as $name => $extension) {
            if ($extension->isEnabled() && empty($this->booted[$name])) {
                $extension->boot($app);
                $this->booted[$name] = true;
            }
        }
    }

    /**
     * Get a registered extension by name.
     */
    public function get(string $name): ?Extension
    {
        return $this->extensions[$name] ?? null;
    }

    /**
     * Get all registered extensions.
     *
     * @return array<string, Extension>
     */
    public function all(): array
    {
        return $this->extensions;
    }

    /**
     * Get only the enabled extensions.
     *
     * @return array<string, Extension>
     */
    public function enabled(): array
    {
        return array_filter($this->extensions, fn(Extension $ext) => $ext->isEnabled());
    }

    /**
     * Check if a specific extension is registered and enabled.
     */
    public function isEnabled(string $name): bool
    {
        return isset($this->extensions[$name]) && $this->extensions[$name]->isEnabled();
    }
}
