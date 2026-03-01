<?php

namespace Badr\ScribeAi;

use Badr\ScribeAi\Console\Commands\InstallCommand;
use Badr\ScribeAi\Console\Commands\ListRunsCommand;
use Badr\ScribeAi\Console\Commands\ProcessUrlCommand;
use Badr\ScribeAi\Console\Commands\PublishApprovedCommand;
use Badr\ScribeAi\Console\Commands\PublishArticleCommand;
use Badr\ScribeAi\Console\Commands\ResumeRunCommand;
use Badr\ScribeAi\Extensions\TelegramApproval\RssReviewCommand;
use Badr\ScribeAi\Extensions\TelegramApproval\SetWebhookCommand;
use Badr\ScribeAi\Extensions\TelegramApproval\TelegramApprovalExtension;
use Badr\ScribeAi\Extensions\TelegramApproval\TelegramPollCommand;
use Badr\ScribeAi\Services\Ai\AiProviderManager;
use Badr\ScribeAi\Services\Ai\AiService;
use Badr\ScribeAi\Services\Ai\ContentRewriter;
use Badr\ScribeAi\Services\Ai\ImageGenerator;
use Badr\ScribeAi\Services\Ai\SeoSuggester;
use Badr\ScribeAi\Services\ExtensionManager;
use Badr\ScribeAi\Services\ImageOptimizer;
use Badr\ScribeAi\Services\Pipeline\ContentPipeline;
use Badr\ScribeAi\Services\Publishing\PublisherManager;
use Badr\ScribeAi\Services\Sources\ContentSourceManager;
use Badr\ScribeAi\Services\WebScraper;
use Illuminate\Support\ServiceProvider;

class ScribeAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/scribe-ai.php',
            'scribe-ai'
        );

        $this->app->singleton(PublisherManager::class);
        $this->app->singleton(ContentSourceManager::class);
        $this->app->singleton(AiProviderManager::class);
        $this->app->singleton(AiService::class, fn($app) => new AiService($app->make(AiProviderManager::class)));
        $this->app->singleton(ContentPipeline::class);
        $this->app->singleton(ImageOptimizer::class);
        $this->app->singleton(WebScraper::class);

        $this->app->singleton(ContentRewriter::class, fn($app) => new ContentRewriter($app->make(AiService::class)));
        $this->app->singleton(SeoSuggester::class, fn($app) => new SeoSuggester($app->make(AiService::class)));
        $this->app->singleton(ImageGenerator::class, fn($app) => new ImageGenerator($app->make(AiProviderManager::class)));

        $this->app->alias(PublisherManager::class, 'scribe-ai');
        $this->app->alias(ContentPipeline::class, 'scribe-pipeline');
        $this->app->alias(ContentSourceManager::class, 'scribe-source');
        $this->app->alias(AiProviderManager::class, 'scribe-ai-provider');

        // ── Extension Manager ────────────────────────────────────────
        $this->app->singleton(ExtensionManager::class);

        /** @var ExtensionManager $extensions */
        $extensions = $this->app->make(ExtensionManager::class);

        // Register built-in extensions
        $extensions->register(new TelegramApprovalExtension(), $this->app);

        // Register user-defined extensions from config
        foreach (config('scribe-ai.custom_extensions', []) as $extensionClass) {
            if (class_exists($extensionClass)) {
                $extensions->register(new $extensionClass(), $this->app);
            }
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/scribe-ai.php' => config_path('scribe-ai.php'),
            ], 'scribe-ai-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'scribe-ai-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $commands = [
                InstallCommand::class,
                ListRunsCommand::class,
                ProcessUrlCommand::class,
                PublishApprovedCommand::class,
                PublishArticleCommand::class,
                ResumeRunCommand::class,
            ];

            if ($this->isTelegramApprovalEnabled()) {
                $commands = array_merge($commands, [
                    RssReviewCommand::class,
                    TelegramPollCommand::class,
                    SetWebhookCommand::class,
                ]);
            }

            $this->commands($commands);
        }

        // Load webhook route when extension is enabled
        // (webhook URL is auto-resolved from APP_URL if not set explicitly)
        if ($this->isTelegramApprovalEnabled()) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/telegram-webhook.php');
        }

        // Boot all registered extensions
        $this->app->make(ExtensionManager::class)->bootAll($this->app);
    }

    /**
     * Check if the Telegram Approval extension is enabled.
     */
    protected function isTelegramApprovalEnabled(): bool
    {
        return (bool) config('scribe-ai.extensions.telegram_approval.enabled', false);
    }
}
