<?php

namespace Bader\ContentPublisher\Contracts;

use Illuminate\Contracts\Foundation\Application;

/**
 * Contract for Scribe AI extensions.
 *
 * Extensions are optional, self-contained modules that add complete
 * workflows on top of the core pipeline. Implement this interface
 * and register your extension in a service provider:
 *
 *     app(\Bader\ContentPublisher\Services\ExtensionManager::class)
 *         ->register(new MyExtension());
 *
 * Extensions are booted lazily — the boot() method is called only
 * when isEnabled() returns true.
 */
interface Extension
{
    /**
     * Unique identifier for this extension (e.g. 'telegram-approval').
     */
    public function name(): string;

    /**
     * Whether the extension is currently enabled.
     *
     * Check config, env vars, or any other condition here.
     */
    public function isEnabled(): bool;

    /**
     * Register bindings into the container.
     *
     * Called during the service-provider register phase
     * only when isEnabled() returns true.
     */
    public function register(Application $app): void;

    /**
     * Boot the extension (register commands, routes, listeners, etc.).
     *
     * Called during the service-provider boot phase
     * only when isEnabled() returns true.
     */
    public function boot(Application $app): void;
}
