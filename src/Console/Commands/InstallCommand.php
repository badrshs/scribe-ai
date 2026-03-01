<?php

namespace Badr\ScribeAi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Interactive installer wizard for Scribe AI.
 *
 * Publishes config & migrations, writes .env variables, and
 * guides the user through AI provider & channel setup.
 */
class InstallCommand extends Command
{
    protected $signature = 'scribe:install
        {--force : Overwrite existing config/migrations}';

    protected $description = 'Install and configure Scribe AI interactively';

    /** Env lines to write at the end of the wizard. */
    protected array $envLines = [];

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Scribe AI — Installation Wizard');
        $this->newLine();

        $this->publishAssets();
        $this->configureAiProvider();
        $this->configurePublishChannels();
        $this->configurePipeline();
        $this->writeEnvFile();

        $this->newLine();
        $this->components->info('Scribe AI installed successfully!');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('  1. Review <comment>config/scribe-ai.php</comment>');
        $this->line('  2. Run <comment>php artisan migrate</comment>');
        $this->line('  3. Try <comment>php artisan scribe:process-url https://example.com --sync</comment>');
        $this->newLine();

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────
    //  Steps
    // ──────────────────────────────────────────────────────────

    protected function publishAssets(): void
    {
        $this->components->task('Publishing config', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'scribe-ai-config',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Publishing migrations', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'scribe-ai-migrations',
                '--force' => $this->option('force'),
            ]);
        });
    }

    protected function configureAiProvider(): void
    {
        $this->newLine();
        $this->components->info('AI Provider');

        $provider = $this->choice(
            'Which AI provider will you use?',
            ['openai', 'claude', 'gemini', 'ollama'],
            'openai',
        );

        $this->envLines['AI_PROVIDER'] = $provider;

        match ($provider) {
            'openai' => $this->configureOpenAi(),
            'claude' => $this->configureClaude(),
            'gemini' => $this->configureGemini(),
            'ollama' => $this->configureOllama(),
        };

        // Ask about separate image provider
        if ($this->confirm('Use a separate provider for image generation?', false)) {
            $imageProvider = $this->choice(
                'Image generation provider',
                ['openai', 'gemini', 'piapi'],
                'openai',
            );

            $this->envLines['AI_IMAGE_PROVIDER'] = $imageProvider;

            if ($imageProvider === 'piapi') {
                $this->configurePiApi();
            } elseif ($imageProvider !== $provider) {
                // Configure the image provider if different from text provider
                match ($imageProvider) {
                    'openai' => $this->configureOpenAi(),
                    'gemini' => $this->configureGemini(),
                    default  => null,
                };
            }
        }

        $model = $this->askWithDefault(
            'Content model (leave blank for provider default)',
            $this->defaultModel($provider),
        );

        $this->envLines['OPENAI_CONTENT_MODEL'] = $model;

        $language = $this->askWithDefault('Output language', 'English');
        $this->envLines['AI_OUTPUT_LANGUAGE'] = $language;
    }

    protected function configureOpenAi(): void
    {
        $key = $this->secret('OpenAI API key');
        if ($key) {
            $this->envLines['OPENAI_API_KEY'] = $key;
        }
    }

    protected function configureClaude(): void
    {
        $key = $this->secret('Anthropic API key');
        if ($key) {
            $this->envLines['ANTHROPIC_API_KEY'] = $key;
        }
    }

    protected function configureGemini(): void
    {
        $key = $this->secret('Google Gemini API key');
        if ($key) {
            $this->envLines['GEMINI_API_KEY'] = $key;
        }
    }

    protected function configureOllama(): void
    {
        $host = $this->askWithDefault('Ollama host URL', 'http://localhost:11434');
        $this->envLines['OLLAMA_HOST'] = $host;
    }

    protected function configurePiApi(): void
    {
        $key = $this->secret('PiAPI API key');
        if ($key) {
            $this->envLines['PIAPI_API_KEY'] = $key;
        }
    }

    protected function configurePublishChannels(): void
    {
        $this->newLine();
        $this->components->info('Publish Channels');

        $channels = $this->choice(
            'Which channels do you want to publish to? (comma-separated)',
            ['log', 'telegram', 'facebook', 'blogger', 'wordpress'],
            'log',
            null,
            true,
        );

        $this->envLines['PUBLISHER_CHANNELS'] = implode(',', $channels);
        $this->envLines['PUBLISHER_DEFAULT_CHANNEL'] = $channels[0];

        foreach ($channels as $channel) {
            match ($channel) {
                'telegram' => $this->configureTelegram(),
                'facebook' => $this->configureFacebook(),
                'blogger'  => $this->configureBlogger(),
                'wordpress' => $this->configureWordPress(),
                default     => null,
            };
        }
    }

    protected function configureTelegram(): void
    {
        $token = $this->secret('Telegram bot token');
        if ($token) {
            $this->envLines['TELEGRAM_BOT_TOKEN'] = $token;
        }

        $chatId = $this->ask('Telegram chat/channel ID');
        if ($chatId) {
            $this->envLines['TELEGRAM_CHAT_ID'] = $chatId;
        }
    }

    protected function configureFacebook(): void
    {
        $pageId = $this->ask('Facebook Page ID');
        if ($pageId) {
            $this->envLines['FACEBOOK_PAGE_ID'] = $pageId;
        }

        $token = $this->secret('Facebook Page Access Token');
        if ($token) {
            $this->envLines['FACEBOOK_PAGE_ACCESS_TOKEN'] = $token;
        }
    }

    protected function configureBlogger(): void
    {
        $blogId = $this->ask('Blogger Blog ID');
        if ($blogId) {
            $this->envLines['BLOGGER_BLOG_ID'] = $blogId;
        }

        $key = $this->secret('Blogger API key');
        if ($key) {
            $this->envLines['BLOGGER_API_KEY'] = $key;
        }
    }

    protected function configureWordPress(): void
    {
        $url = $this->ask('WordPress site URL (e.g. https://myblog.com)');
        if ($url) {
            $this->envLines['WORDPRESS_URL'] = $url;
        }

        $user = $this->ask('WordPress username');
        if ($user) {
            $this->envLines['WORDPRESS_USERNAME'] = $user;
        }

        $pass = $this->secret('WordPress application password');
        if ($pass) {
            $this->envLines['WORDPRESS_PASSWORD'] = $pass;
        }
    }

    protected function configurePipeline(): void
    {
        $this->newLine();
        $this->components->info('Pipeline Settings');

        if ($this->confirm('Enable pipeline run tracking? (enables resume on failure)', true)) {
            $this->envLines['PIPELINE_TRACK_RUNS'] = 'true';
        } else {
            $this->envLines['PIPELINE_TRACK_RUNS'] = 'false';
        }

        if ($this->confirm('Enable image optimization?', true)) {
            $this->envLines['IMAGE_OPTIMIZE'] = 'true';
        } else {
            $this->envLines['IMAGE_OPTIMIZE'] = 'false';
        }

        if ($this->confirm('Enable Telegram approval extension?', false)) {
            $this->envLines['TELEGRAM_APPROVAL_ENABLED'] = 'true';
        }
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    protected function writeEnvFile(): void
    {
        $envPath = $this->laravel->basePath('.env');

        if (! File::exists($envPath)) {
            $this->warn('.env file not found — skipping env variable injection.');
            $this->line('  Add these to your .env manually:');
            foreach ($this->envLines as $key => $value) {
                $this->line("  <comment>{$key}={$value}</comment>");
            }

            return;
        }

        $envContent = File::get($envPath);
        $added = [];
        $updated = [];

        foreach ($this->envLines as $key => $value) {
            // Wrap in quotes if value contains spaces or special chars
            $safeValue = $this->needsQuotes($value) ? "\"{$value}\"" : $value;

            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$safeValue}", $envContent);
                $updated[] = $key;
            } else {
                $envContent .= "\n{$key}={$safeValue}";
                $added[] = $key;
            }
        }

        File::put($envPath, $envContent);

        $this->components->task('Writing .env variables', fn() => true);

        if (! empty($updated)) {
            $this->line('  <fg=yellow>Updated:</> ' . implode(', ', $updated));
        }
        if (! empty($added)) {
            $this->line('  <fg=green>Added:</> ' . implode(', ', $added));
        }
    }

    protected function defaultModel(string $provider): string
    {
        return match ($provider) {
            'claude' => 'claude-sonnet-4-20250514',
            'gemini' => 'gemini-2.0-flash',
            'ollama' => 'llama3.1',
            default  => 'gpt-4o-mini',
        };
    }

    protected function askWithDefault(string $question, string $default): string
    {
        $answer = $this->ask($question, $default);

        return $answer ?: $default;
    }

    protected function needsQuotes(string $value): bool
    {
        return (bool) preg_match('/[\s"\'#]/', $value);
    }
}
