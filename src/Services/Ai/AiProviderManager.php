<?php

namespace Bader\ContentPublisher\Services\Ai;

use Bader\ContentPublisher\Contracts\AiProvider;
use Bader\ContentPublisher\Services\Ai\Providers\ClaudeProvider;
use Bader\ContentPublisher\Services\Ai\Providers\GeminiProvider;
use Bader\ContentPublisher\Services\Ai\Providers\OllamaProvider;
use Bader\ContentPublisher\Services\Ai\Providers\OpenAiProvider;
use Bader\ContentPublisher\Services\Ai\Providers\PiApiProvider;
use InvalidArgumentException;

/**
 * Manages AI provider drivers.
 *
 * Resolves the active provider from config and allows runtime switching.
 * Custom providers can be registered via extend().
 *
 * Usage:
 *   $manager = app(AiProviderManager::class);
 *   $provider = $manager->provider();            // default provider
 *   $provider = $manager->provider('claude');     // specific provider
 *
 *   // Register a custom provider
 *   $manager->extend('mistral', fn($config) => new MistralProvider($config));
 */
class AiProviderManager
{
    /** @var array<string, AiProvider> */
    protected array $resolved = [];

    /** @var array<string, \Closure> */
    protected array $customCreators = [];

    /**
     * Get a provider instance by name (default from config).
     */
    public function provider(?string $name = null): AiProvider
    {
        $name ??= $this->getDefaultProvider();

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        return $this->resolved[$name] = $this->resolve($name);
    }

    /**
     * Get the provider used for image generation.
     *
     * Returns the configured image provider, falling back to the default
     * text provider if the image provider supports image generation.
     */
    public function imageProvider(): AiProvider
    {
        $name = config('scribe-ai.ai.image_provider');

        if ($name) {
            return $this->provider($name);
        }

        // Fall back to the default text provider
        $default = $this->provider();

        if ($default->supportsImageGeneration()) {
            return $default;
        }

        // Last resort: try OpenAI (most likely to support image gen)
        return $this->provider('openai');
    }

    /**
     * Register a custom provider creator.
     *
     * @param  string  $name  Provider name
     * @param  \Closure(array): AiProvider  $creator  Factory receiving config array
     */
    public function extend(string $name, \Closure $creator): static
    {
        $this->customCreators[$name] = $creator;

        // Clear cache if already resolved
        unset($this->resolved[$name]);

        return $this;
    }

    /**
     * Get the default provider name from config.
     */
    public function getDefaultProvider(): string
    {
        return config('scribe-ai.ai.provider', 'openai');
    }

    /**
     * Get all available provider names (built-in + custom).
     *
     * @return string[]
     */
    public function available(): array
    {
        return array_unique(array_merge(
            ['openai', 'claude', 'gemini', 'ollama', 'piapi'],
            array_keys($this->customCreators),
        ));
    }

    /**
     * Resolve a provider by name.
     */
    protected function resolve(string $name): AiProvider
    {
        $config = config("scribe-ai.ai.providers.{$name}", []);

        // Merge top-level api_key for backward compatibility
        if (empty($config['api_key']) && $name === 'openai') {
            $config['api_key'] = config('scribe-ai.ai.api_key', '');
        }

        // Custom creator?
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($config);
        }

        return match ($name) {
            'openai' => new OpenAiProvider($config),
            'claude' => new ClaudeProvider($config),
            'gemini' => new GeminiProvider($config),
            'ollama' => new OllamaProvider($config),
            'piapi'  => new PiApiProvider($config),
            default => throw new InvalidArgumentException("Unknown AI provider: {$name}"),
        };
    }
}
