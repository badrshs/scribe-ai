<?php

namespace Bader\ContentPublisher\Tests\Feature;

use Bader\ContentPublisher\Contracts\AiProvider;
use Bader\ContentPublisher\Services\Ai\AiProviderManager;
use Bader\ContentPublisher\Services\Ai\Providers\ClaudeProvider;
use Bader\ContentPublisher\Services\Ai\Providers\GeminiProvider;
use Bader\ContentPublisher\Services\Ai\Providers\OllamaProvider;
use Bader\ContentPublisher\Services\Ai\Providers\OpenAiProvider;
use Bader\ContentPublisher\Services\Ai\Providers\PiApiProvider;
use Bader\ContentPublisher\Tests\TestCase;
use InvalidArgumentException;

class AiProviderManagerTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scribe-ai.ai.provider', 'openai');
        $app['config']->set('scribe-ai.ai.providers.openai', [
            'api_key' => 'sk-test-key',
            'base_url' => 'https://api.openai.com/v1',
        ]);
        $app['config']->set('scribe-ai.ai.providers.claude', [
            'api_key' => 'sk-ant-test',
            'base_url' => 'https://api.anthropic.com/v1',
            'api_version' => '2023-06-01',
        ]);
        $app['config']->set('scribe-ai.ai.providers.gemini', [
            'api_key' => 'gemini-test-key',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);
        $app['config']->set('scribe-ai.ai.providers.ollama', [
            'host' => 'http://localhost:11434',
        ]);
        $app['config']->set('scribe-ai.ai.providers.piapi', [
            'api_key' => 'piapi-test-key',
            'base_url' => 'https://api.piapi.ai',
        ]);
    }

    public function test_resolves_default_provider(): void
    {
        $manager = app(AiProviderManager::class);

        $provider = $manager->provider();

        $this->assertInstanceOf(OpenAiProvider::class, $provider);
        $this->assertSame('openai', $provider->name());
    }

    public function test_resolves_openai_provider_by_name(): void
    {
        $manager = app(AiProviderManager::class);

        $provider = $manager->provider('openai');

        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function test_resolves_claude_provider(): void
    {
        $manager = app(AiProviderManager::class);

        $provider = $manager->provider('claude');

        $this->assertInstanceOf(ClaudeProvider::class, $provider);
        $this->assertSame('claude', $provider->name());
    }

    public function test_resolves_gemini_provider(): void
    {
        $manager = app(AiProviderManager::class);

        $provider = $manager->provider('gemini');

        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertSame('gemini', $provider->name());
    }

    public function test_resolves_ollama_provider(): void
    {
        $manager = app(AiProviderManager::class);

        $provider = $manager->provider('ollama');

        $this->assertInstanceOf(OllamaProvider::class, $provider);
        $this->assertSame('ollama', $provider->name());
    }

    public function test_resolves_piapi_provider(): void
    {
        $manager = app(AiProviderManager::class);

        $provider = $manager->provider('piapi');

        $this->assertInstanceOf(PiApiProvider::class, $provider);
        $this->assertSame('piapi', $provider->name());
        $this->assertTrue($provider->supportsImageGeneration());
    }

    public function test_piapi_does_not_support_chat(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not support chat');

        $provider = app(AiProviderManager::class)->provider('piapi');
        $provider->chat([['role' => 'user', 'content' => 'Hello']], 'flux-1');
    }

    public function test_caches_resolved_providers(): void
    {
        $manager = app(AiProviderManager::class);

        $first = $manager->provider('openai');
        $second = $manager->provider('openai');

        $this->assertSame($first, $second);
    }

    public function test_throws_for_unknown_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AiProviderManager::class)->provider('nonexistent');
    }

    public function test_custom_provider_via_extend(): void
    {
        $manager = app(AiProviderManager::class);

        $customProvider = \Mockery::mock(AiProvider::class);
        $customProvider->shouldReceive('name')->andReturn('custom');

        $manager->extend('custom', fn(array $config) => $customProvider);

        $resolved = $manager->provider('custom');

        $this->assertSame($customProvider, $resolved);
    }

    public function test_extend_clears_cache(): void
    {
        $manager = app(AiProviderManager::class);

        // Resolve openai first
        $original = $manager->provider('openai');

        // Override openai with custom
        $custom = \Mockery::mock(AiProvider::class);
        $custom->shouldReceive('name')->andReturn('openai');
        $manager->extend('openai', fn(array $config) => $custom);

        $overridden = $manager->provider('openai');

        $this->assertSame($custom, $overridden);
        $this->assertNotSame($original, $overridden);
    }

    public function test_available_includes_all_built_in_and_custom(): void
    {
        $manager = app(AiProviderManager::class);

        $manager->extend('perplexity', fn($config) => \Mockery::mock(AiProvider::class));

        $available = $manager->available();

        $this->assertContains('openai', $available);
        $this->assertContains('claude', $available);
        $this->assertContains('gemini', $available);
        $this->assertContains('ollama', $available);
        $this->assertContains('piapi', $available);
        $this->assertContains('perplexity', $available);
    }

    public function test_default_provider_from_config(): void
    {
        config(['scribe-ai.ai.provider' => 'claude']);

        // Create a fresh manager to read updated config
        $manager = new AiProviderManager();

        $this->assertSame('claude', $manager->getDefaultProvider());
    }

    public function test_image_provider_uses_configured_value(): void
    {
        config(['scribe-ai.ai.image_provider' => 'piapi']);

        $manager = new AiProviderManager();
        $provider = $manager->imageProvider();

        $this->assertInstanceOf(PiApiProvider::class, $provider);
    }

    public function test_image_provider_falls_back_to_default_when_supported(): void
    {
        config(['scribe-ai.ai.image_provider' => null]);
        config(['scribe-ai.ai.provider' => 'openai']);

        $manager = new AiProviderManager();
        $provider = $manager->imageProvider();

        // OpenAI supports images, so it should be used
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function test_image_provider_falls_back_to_openai_when_default_no_image_support(): void
    {
        config(['scribe-ai.ai.image_provider' => null]);
        config(['scribe-ai.ai.provider' => 'claude']);

        $manager = new AiProviderManager();
        $provider = $manager->imageProvider();

        // Claude doesn't support images → falls back to OpenAI
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }

    public function test_provider_supports_image_generation_flags(): void
    {
        $manager = app(AiProviderManager::class);

        $this->assertTrue($manager->provider('openai')->supportsImageGeneration());
        $this->assertFalse($manager->provider('claude')->supportsImageGeneration());
        $this->assertTrue($manager->provider('gemini')->supportsImageGeneration());
        $this->assertFalse($manager->provider('ollama')->supportsImageGeneration());
        $this->assertTrue($manager->provider('piapi')->supportsImageGeneration());
    }

    public function test_backward_compat_top_level_api_key_merged_into_openai(): void
    {
        // Clear provider-level key, set only top-level key
        config(['scribe-ai.ai.providers.openai.api_key' => '']);
        config(['scribe-ai.ai.api_key' => 'sk-top-level-key']);

        $manager = new AiProviderManager();
        $provider = $manager->provider('openai');

        // Should resolve without error (key merged from top-level)
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
    }
}
