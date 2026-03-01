<?php

namespace Badr\ScribeAi\Tests\Feature;

use Badr\ScribeAi\Console\Commands\InstallCommand;
use Badr\ScribeAi\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Testable subclass that replaces secret() with ask() so that
 * CommandTester can feed answers via an in-memory stream.
 *
 * On Windows, Symfony's QuestionHelper::getHiddenResponse() shells
 * out to hiddeninput.exe which blocks when there is no real console.
 * This subclass side-steps that while still exercising every other
 * part of the Symfony rendering pipeline (including writePrompt).
 */
class TestableInstallCommand extends InstallCommand
{
    public function secret($question, $fallback = true)
    {
        return $this->ask($question);
    }
}

/**
 * End-to-end tests for the scribe:install command.
 *
 * Uses Symfony's CommandTester instead of Laravel's expectsChoice /
 * expectsQuestion mocks.  This exercises the REAL Symfony console
 * rendering pipeline (writePrompt → doAsk → validateAttempts) and
 * catches bugs that mocks silently skip — such as the multiselect
 * ChoiceQuestion default-index rendering bug in writePrompt().
 */
class InstallCommandTest extends TestCase
{
    protected string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the testable subclass so CommandTester uses it
        $this->app->singleton(TestableInstallCommand::class);
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand($this->app->make(TestableInstallCommand::class));

        $this->envPath = $this->app->basePath('.env');
        File::put($this->envPath, "APP_NAME=TestApp\nAPP_KEY=base64:test\n");
    }

    protected function tearDown(): void
    {
        if (File::exists($this->envPath)) {
            File::delete($this->envPath);
        }

        parent::tearDown();
    }

    /**
     * Run install with piped stdin inputs through the real Symfony renderer.
     *
     * @return array{0: int, 1: string, 2: string|null}  [exitCode, display, envContent]
     */
    protected function runInstall(array $inputs): array
    {
        $command = \Artisan::all()['scribe:install'];
        $tester  = new CommandTester($command);
        $tester->setInputs($inputs);

        $exitCode = $tester->execute(['--force' => true]);
        $display  = $tester->getDisplay();
        $env      = File::exists($this->envPath) ? File::get($this->envPath) : null;

        return [$exitCode, $display, $env];
    }

    /**
     * Assert the install ran cleanly — no Symfony rendering errors, no [ERROR] blocks.
     */
    protected function assertCleanInstall(int $exitCode, string $display): void
    {
        $this->assertStringNotContainsString(
            'Undefined array key',
            $display,
            'Symfony rendering error detected — likely a ChoiceQuestion default bug',
        );
        $this->assertStringNotContainsString('[ERROR]', $display, 'Error block found in command output');
        $this->assertStringContainsString('installed successfully', $display, 'Success message missing');
        $this->assertEquals(0, $exitCode, "Non-zero exit code. Output:\n{$display}");
    }

    // ──────────────────────────────────────────────────────────
    //  Registration
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_command_is_registered(): void
    {
        $this->assertArrayHasKey('scribe:install', \Artisan::all());
    }

    // ──────────────────────────────────────────────────────────
    //  OpenAI + default log channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_openai_with_log_channel(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',            // provider: openai
            'sk-test-key',  // openai api key
            'no',           // separate image provider
            '',             // content model (accept default)
            '',             // output language (accept default)
            '0',            // channel: log
            'yes',          // pipeline run tracking
            'yes',          // image optimisation
            'no',           // telegram approval
        ]);

        $this->assertCleanInstall($exit, $output);

        $this->assertStringContainsString('AI_PROVIDER=openai', $env);
        $this->assertStringContainsString('OPENAI_API_KEY=sk-test-key', $env);
        $this->assertStringContainsString('OPENAI_CONTENT_MODEL=gpt-4o-mini', $env);
        $this->assertStringContainsString('AI_OUTPUT_LANGUAGE=English', $env);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log', $env);
        $this->assertStringContainsString('PUBLISHER_DEFAULT_CHANNEL=log', $env);
        $this->assertStringContainsString('PIPELINE_TRACK_RUNS=true', $env);
        $this->assertStringContainsString('IMAGE_OPTIMIZE=true', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Claude
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_claude_provider(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '1',              // provider: claude
            'sk-ant-key',     // anthropic api key
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_PROVIDER=claude', $env);
        $this->assertStringContainsString('ANTHROPIC_API_KEY=sk-ant-key', $env);
        $this->assertStringContainsString('OPENAI_CONTENT_MODEL=claude-sonnet-4-20250514', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Gemini
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_gemini_provider(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '2',             // provider: gemini
            'AIza-key',      // gemini api key
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_PROVIDER=gemini', $env);
        $this->assertStringContainsString('GEMINI_API_KEY=AIza-key', $env);
        $this->assertStringContainsString('OPENAI_CONTENT_MODEL=gemini-2.0-flash', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Ollama
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_ollama_provider(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '3',    // provider: ollama
            '',     // host URL (accept default)
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_PROVIDER=ollama', $env);
        $this->assertStringContainsString('OLLAMA_HOST=http://localhost:11434', $env);
        $this->assertStringContainsString('OPENAI_CONTENT_MODEL=llama3.1', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Separate PiAPI image provider
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_piapi_image_provider(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',            // provider: openai
            'sk-key',       // openai key
            'yes',          // separate image provider
            '2',            // image provider: piapi
            'piapi-key',    // piapi key
            '',             // model
            '',             // language
            '0',            // channel: log
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=piapi', $env);
        $this->assertStringContainsString('PIAPI_API_KEY=piapi-key', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Different image provider triggers its own credentials
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_different_image_provider_prompts_credentials(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '1',                    // provider: claude
            'sk-ant-key',           // anthropic key
            'yes',                  // separate image provider
            '0',                    // image provider: openai
            'sk-openai-for-images', // openai key (for images)
            '',                     // model
            '',                     // language
            '0',                    // channel: log
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_PROVIDER=claude', $env);
        $this->assertStringContainsString('ANTHROPIC_API_KEY=sk-ant-key', $env);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=openai', $env);
        $this->assertStringContainsString('OPENAI_API_KEY=sk-openai-for-images', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Same image provider as text — no extra config prompt
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_same_image_provider_skips_duplicate_config(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',        // provider: openai
            'sk-key',   // openai key
            'yes',      // separate image provider
            '0',        // image provider: openai (same)
            '',         // model
            '',         // language
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=openai', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Telegram channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_telegram_channel(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '1',                  // channel: telegram
            'bot123:TOKEN',       // bot token
            '-1001234567890',     // chat id
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=telegram', $env);
        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=bot123:TOKEN', $env);
        $this->assertStringContainsString('TELEGRAM_CHAT_ID=-1001234567890', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Facebook channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_facebook_channel(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '2',             // channel: facebook
            '123456789',     // page id
            'EAA-token',     // page access token
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=facebook', $env);
        $this->assertStringContainsString('FACEBOOK_PAGE_ID=123456789', $env);
        $this->assertStringContainsString('FACEBOOK_PAGE_ACCESS_TOKEN=EAA-token', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Blogger channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_blogger_channel(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '3',              // channel: blogger
            'blog-12345',     // blog id
            'AIza-blog-key',  // api key
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=blogger', $env);
        $this->assertStringContainsString('BLOGGER_BLOG_ID=blog-12345', $env);
        $this->assertStringContainsString('BLOGGER_API_KEY=AIza-blog-key', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  WordPress channel
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_wordpress_channel(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '4',                    // channel: wordpress
            'https://myblog.com',   // wp url
            'admin',                // username
            'wp-app-pass',          // password
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=wordpress', $env);
        $this->assertStringContainsString('WORDPRESS_URL=https://myblog.com', $env);
        $this->assertStringContainsString('WORDPRESS_USERNAME=admin', $env);
        $this->assertStringContainsString('WORDPRESS_PASSWORD=wp-app-pass', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Multiple channels
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_multiple_channels(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '0,1',            // channels: log + telegram
            'bot:TOKEN',      // telegram bot token
            '-100123',        // telegram chat id
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log,telegram', $env);
        $this->assertStringContainsString('PUBLISHER_DEFAULT_CHANNEL=log', $env);
        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=bot:TOKEN', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  All channels — comprehensive end-to-end
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_all_channels(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-full',
            'yes',             // separate image provider
            '2',               // piapi
            'piapi-key',
            '',
            '',
            '0,1,2,3,4',      // all channels
            'bot:T',           // telegram
            '-100',
            'fb-id',           // facebook
            'fb-token',
            'blog-id',         // blogger
            'blog-key',
            'https://wp.test', // wordpress
            'admin',
            'wp-pass',
            'yes',
            'yes',
            'yes',             // telegram approval
        ]);

        $this->assertCleanInstall($exit, $output);

        // Providers
        $this->assertStringContainsString('AI_PROVIDER=openai', $env);
        $this->assertStringContainsString('OPENAI_API_KEY=sk-full', $env);
        $this->assertStringContainsString('AI_IMAGE_PROVIDER=piapi', $env);
        $this->assertStringContainsString('PIAPI_API_KEY=piapi-key', $env);

        // Channels
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log,telegram,facebook,blogger,wordpress', $env);
        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN=bot:T', $env);
        $this->assertStringContainsString('TELEGRAM_CHAT_ID=-100', $env);
        $this->assertStringContainsString('FACEBOOK_PAGE_ID=fb-id', $env);
        $this->assertStringContainsString('BLOGGER_BLOG_ID=blog-id', $env);
        $this->assertStringContainsString('WORDPRESS_URL=https://wp.test', $env);
        $this->assertStringContainsString('WORDPRESS_USERNAME=admin', $env);

        // Pipeline
        $this->assertStringContainsString('PIPELINE_TRACK_RUNS=true', $env);
        $this->assertStringContainsString('IMAGE_OPTIMIZE=true', $env);
        $this->assertStringContainsString('TELEGRAM_APPROVAL_ENABLED=true', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Pipeline features all disabled
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_disabled_pipeline_features(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '0',
            'no',   // pipeline tracking OFF
            'no',   // image optimisation OFF
            'no',   // telegram approval OFF
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PIPELINE_TRACK_RUNS=false', $env);
        $this->assertStringContainsString('IMAGE_OPTIMIZE=false', $env);
        $this->assertStringNotContainsString('TELEGRAM_APPROVAL_ENABLED', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Telegram approval enabled
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_telegram_approval(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'yes',  // telegram approval ON
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('TELEGRAM_APPROVAL_ENABLED=true', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Missing .env file
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_handles_missing_env_file(): void
    {
        File::delete($this->envPath);

        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertNull($env);
        $this->assertStringContainsString('.env file not found', $output);
    }

    // ──────────────────────────────────────────────────────────
    //  Env updates existing keys (no duplicates)
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_updates_existing_env_keys(): void
    {
        File::put($this->envPath, "APP_NAME=TestApp\nAI_PROVIDER=claude\nOPENAI_API_KEY=old-key\n");

        [$exit, $output, $env] = $this->runInstall([
            '0',
            'new-key',
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_PROVIDER=openai', $env);
        $this->assertStringContainsString('OPENAI_API_KEY=new-key', $env);
        $this->assertStringNotContainsString('AI_PROVIDER=claude', $env);
        $this->assertStringNotContainsString('OPENAI_API_KEY=old-key', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Custom output language
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_custom_language(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            'Arabic',   // custom language
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('AI_OUTPUT_LANGUAGE=Arabic', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Multiselect default rendering — THE bug scenario
    //
    //  Pressing Enter on the multiselect channel choice triggers
    //  SymfonyQuestionHelper::writePrompt() to render the default.
    //  Before the fix (default = 'log' string), Symfony did
    //  $choices['log'] on a numeric-keyed array → Undefined array
    //  key → infinite retry loop. With the fix (default = 0),
    //  $choices[0] resolves correctly.
    //
    //  This test sends empty input ('') for the channel choice,
    //  forcing Symfony to use and RENDER the default value.
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_multiselect_default_renders_without_errors(): void
    {
        [$exit, $output, $env] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '',     // accept default channel — exercises writePrompt() default rendering
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
        $this->assertStringContainsString('PUBLISHER_CHANNELS=log', $env);
    }

    // ──────────────────────────────────────────────────────────
    //  Force flag
    // ──────────────────────────────────────────────────────────

    #[Test]
    public function install_with_force_flag(): void
    {
        [$exit, $output] = $this->runInstall([
            '0',
            'sk-key',
            'no',
            '',
            '',
            '0',
            'yes',
            'yes',
            'no',
        ]);

        $this->assertCleanInstall($exit, $output);
    }
}
