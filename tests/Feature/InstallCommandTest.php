<?php

namespace Badr\ScribeAi\Tests\Feature;

use Badr\ScribeAi\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class InstallCommandTest extends TestCase
{
    protected string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a temp .env file for testing
        $this->envPath = $this->app->basePath('.env');

        // Create a minimal .env for the wizard to write into
        File::put($this->envPath, "APP_NAME=TestApp\nAPP_KEY=base64:test\n");
    }

    protected function tearDown(): void
    {
        // Clean up the temp .env
        if (File::exists($this->envPath)) {
            File::delete($this->envPath);
        }

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────
    //  Basic execution
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_command_exists_and_is_registered(): void
    {
        $commands = \Artisan::all();
        $this->assertArrayHasKey('scribe:install', $commands);
    }

    #[Test]
    public function install_command_runs_with_defaults_interactively(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-default-test')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();
    }

    // ──────────────────────────────────────────────────────────
    //  OpenAI provider flow
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_openai_provider(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key-123')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_PROVIDER=openai', $envContent);
        $this->assertStringContainsString('OPENAI_API_KEY=sk-test-key-123', $envContent);
        $this->assertStringContainsString('OPENAI_CONTENT_MODEL=gpt-4o-mini', $envContent);
        $this->assertStringContainsString('AI_OUTPUT_LANGUAGE=English', $envContent);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log', $envContent);
        $this->assertStringContainsString('PUBLISHER_DEFAULT_CHANNEL=log', $envContent);
        $this->assertStringContainsString('PIPELINE_TRACK_RUNS=true', $envContent);
        $this->assertStringContainsString('IMAGE_OPTIMIZE=true', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Claude provider flow
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_claude_provider(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'claude', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('Anthropic API key', 'sk-ant-test-123')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'claude-sonnet-4-20250514')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_PROVIDER=claude', $envContent);
        $this->assertStringContainsString('ANTHROPIC_API_KEY=sk-ant-test-123', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Gemini provider flow
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_gemini_provider(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'gemini', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('Google Gemini API key', 'AIza-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gemini-2.0-flash')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_PROVIDER=gemini', $envContent);
        $this->assertStringContainsString('GEMINI_API_KEY=AIza-test-key', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Ollama provider flow
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_ollama_provider(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'ollama', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('Ollama host URL', 'http://localhost:11434')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'llama3.1')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_PROVIDER=ollama', $envContent);
        $this->assertStringContainsString('OLLAMA_HOST=http://localhost:11434', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Image provider - PiAPI
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_separate_piapi_image_provider(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-123')
            ->expectsConfirmation('Use a separate provider for image generation?', 'yes')
            ->expectsChoice('Image generation provider', 'piapi', ['openai', 'gemini', 'piapi'])
            ->expectsQuestion('PiAPI API key', 'piapi-test-key')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=piapi', $envContent);
        $this->assertStringContainsString('PIAPI_API_KEY=piapi-test-key', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Telegram channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_telegram_channel(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['telegram'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsQuestion('Telegram bot token', 'bot123:ABC')
            ->expectsQuestion('Telegram chat/channel ID', '-1001234567890')
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=telegram', $envContent);
        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=bot123:ABC', $envContent);
        $this->assertStringContainsString('TELEGRAM_CHAT_ID=-1001234567890', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Facebook channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_facebook_channel(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['facebook'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsQuestion('Facebook Page ID', '123456789')
            ->expectsQuestion('Facebook Page Access Token', 'EAA-test-token')
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('FACEBOOK_PAGE_ID=123456789', $envContent);
        $this->assertStringContainsString('FACEBOOK_PAGE_ACCESS_TOKEN=EAA-test-token', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Blogger channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_blogger_channel(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['blogger'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsQuestion('Blogger Blog ID', 'blog-12345')
            ->expectsQuestion('Blogger API key', 'AIza-blogger-key')
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('BLOGGER_BLOG_ID=blog-12345', $envContent);
        $this->assertStringContainsString('BLOGGER_API_KEY=AIza-blogger-key', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  WordPress channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_wordpress_channel(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['wordpress'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsQuestion('WordPress site URL (e.g. https://myblog.com)', 'https://myblog.com')
            ->expectsQuestion('WordPress username', 'admin')
            ->expectsQuestion('WordPress application password', 'wp-app-pass')
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('WORDPRESS_URL=https://myblog.com', $envContent);
        $this->assertStringContainsString('WORDPRESS_USERNAME=admin', $envContent);
        $this->assertStringContainsString('WORDPRESS_PASSWORD=wp-app-pass', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Multiple channels
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_configures_multiple_channels(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log', 'telegram'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsQuestion('Telegram bot token', 'bot:TOKEN')
            ->expectsQuestion('Telegram chat/channel ID', '-100123')
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log,telegram', $envContent);
        $this->assertStringContainsString('PUBLISHER_DEFAULT_CHANNEL=log', $envContent);
        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=bot:TOKEN', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Pipeline settings — all disabled
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_can_disable_pipeline_features(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'no')
            ->expectsConfirmation('Enable image optimization?', 'no')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('PIPELINE_TRACK_RUNS=false', $envContent);
        $this->assertStringContainsString('IMAGE_OPTIMIZE=false', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Telegram approval extension
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_enables_telegram_approval(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'yes')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('TELEGRAM_APPROVAL_ENABLED=true', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Env file doesn't exist
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_handles_missing_env_file(): void
    {
        // Remove the .env file
        File::delete($this->envPath);

        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        // .env should not exist (was deleted), wizard should warn and skip
        $this->assertFalse(File::exists($this->envPath));
    }

    // ──────────────────────────────────────────────────────────
    //  Env file updates existing keys
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_updates_existing_env_keys(): void
    {
        // Pre-populate .env with existing keys
        File::put($this->envPath, "APP_NAME=TestApp\nAI_PROVIDER=claude\nOPENAI_API_KEY=old-key\n");

        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'new-key-123')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        // Should update existing key, not duplicate
        $this->assertStringContainsString('AI_PROVIDER=openai', $envContent);
        $this->assertStringContainsString('OPENAI_API_KEY=new-key-123', $envContent);
        $this->assertStringNotContainsString('AI_PROVIDER=claude', $envContent);
        $this->assertStringNotContainsString('OPENAI_API_KEY=old-key', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Force flag
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_accepts_force_flag(): void
    {
        $this->artisan('scribe:install', ['--force' => true])
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-force-test')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();
    }

    // ──────────────────────────────────────────────────────────
    //  Image provider same as text provider (no extra config)
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_with_same_image_and_text_provider_skips_duplicate_config(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-test-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'yes')
            ->expectsChoice('Image generation provider', 'openai', ['openai', 'gemini', 'piapi'])
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=openai', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Image provider different from text provider
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_with_different_image_provider_prompts_credentials(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'claude', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('Anthropic API key', 'sk-ant-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'yes')
            ->expectsChoice('Image generation provider', 'openai', ['openai', 'gemini', 'piapi'])
            ->expectsQuestion('OpenAI API key', 'sk-openai-for-images')
            ->expectsQuestion('Content model (leave blank for provider default)', 'claude-sonnet-4-20250514')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_PROVIDER=claude', $envContent);
        $this->assertStringContainsString('ANTHROPIC_API_KEY=sk-ant-key', $envContent);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=openai', $envContent);
        $this->assertStringContainsString('OPENAI_API_KEY=sk-openai-for-images', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Custom language
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_accepts_custom_language(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-key')
            ->expectsConfirmation('Use a separate provider for image generation?', 'no')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'Arabic')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'no')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);
        $this->assertStringContainsString('AI_OUTPUT_LANGUAGE=Arabic', $envContent);
    }

    // ──────────────────────────────────────────────────────────
    //  Full end-to-end with all channels
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_full_configuration_with_all_channels(): void
    {
        $this->artisan('scribe:install')
            ->expectsChoice('Which AI provider will you use?', 'openai', ['openai', 'claude', 'gemini', 'ollama'])
            ->expectsQuestion('OpenAI API key', 'sk-full-test')
            ->expectsConfirmation('Use a separate provider for image generation?', 'yes')
            ->expectsChoice('Image generation provider', 'piapi', ['openai', 'gemini', 'piapi'])
            ->expectsQuestion('PiAPI API key', 'piapi-full-test')
            ->expectsQuestion('Content model (leave blank for provider default)', 'gpt-4o-mini')
            ->expectsQuestion('Output language', 'English')
            ->expectsChoice('Which channels do you want to publish to? (comma-separated)', ['log', 'telegram', 'facebook', 'blogger', 'wordpress'], ['log', 'telegram', 'facebook', 'blogger', 'wordpress'])
            ->expectsQuestion('Telegram bot token', 'bot:T')
            ->expectsQuestion('Telegram chat/channel ID', '-100')
            ->expectsQuestion('Facebook Page ID', 'fb-id')
            ->expectsQuestion('Facebook Page Access Token', 'fb-token')
            ->expectsQuestion('Blogger Blog ID', 'blog-id')
            ->expectsQuestion('Blogger API key', 'blog-key')
            ->expectsQuestion('WordPress site URL (e.g. https://myblog.com)', 'https://wp.test')
            ->expectsQuestion('WordPress username', 'admin')
            ->expectsQuestion('WordPress application password', 'wp-pass')
            ->expectsConfirmation('Enable pipeline run tracking? (enables resume on failure)', 'yes')
            ->expectsConfirmation('Enable image optimization?', 'yes')
            ->expectsConfirmation('Enable Telegram approval extension?', 'yes')
            ->assertSuccessful();

        $envContent = File::get($this->envPath);

        // Providers
        $this->assertStringContainsString('AI_PROVIDER=openai', $envContent);
        $this->assertStringContainsString('OPENAI_API_KEY=sk-full-test', $envContent);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=piapi', $envContent);
        $this->assertStringContainsString('PIAPI_API_KEY=piapi-full-test', $envContent);

        // Channels
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log,telegram,facebook,blogger,wordpress', $envContent);
        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=bot:T', $envContent);
        $this->assertStringContainsString('TELEGRAM_CHAT_ID=-100', $envContent);
        $this->assertStringContainsString('FACEBOOK_PAGE_ID=fb-id', $envContent);
        $this->assertStringContainsString('BLOGGER_BLOG_ID=blog-id', $envContent);
        $this->assertStringContainsString('WORDPRESS_URL=https://wp.test', $envContent);
        $this->assertStringContainsString('WORDPRESS_USERNAME=admin', $envContent);

        // Pipeline
        $this->assertStringContainsString('PIPELINE_TRACK_RUNS=true', $envContent);
        $this->assertStringContainsString('IMAGE_OPTIMIZE=true', $envContent);
        $this->assertStringContainsString('TELEGRAM_APPROVAL_ENABLED=true', $envContent);
    }
}
