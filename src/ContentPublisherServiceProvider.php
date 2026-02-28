<?php

namespace Bader\ContentPublisher;

use Bader\ContentPublisher\Console\Commands\ListRunsCommand;
use Bader\ContentPublisher\Console\Commands\ProcessUrlCommand;
use Bader\ContentPublisher\Console\Commands\PublishApprovedCommand;
use Bader\ContentPublisher\Console\Commands\PublishArticleCommand;
use Bader\ContentPublisher\Console\Commands\ResumeRunCommand;
use Bader\ContentPublisher\Services\Ai\AiService;
use Bader\ContentPublisher\Services\Ai\ContentRewriter;
use Bader\ContentPublisher\Services\Ai\ImageGenerator;
use Bader\ContentPublisher\Services\Ai\SeoSuggester;
use Bader\ContentPublisher\Services\ImageOptimizer;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Bader\ContentPublisher\Services\Publishing\PublisherManager;
use Bader\ContentPublisher\Services\Sources\ContentSourceManager;
use Bader\ContentPublisher\Services\WebScraper;
use Illuminate\Support\ServiceProvider;

class ContentPublisherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/scribe-ai.php',
            'scribe-ai'
        );

        $this->app->singleton(PublisherManager::class);
        $this->app->singleton(ContentSourceManager::class);
        $this->app->singleton(AiService::class);
        $this->app->singleton(ContentPipeline::class);
        $this->app->singleton(ImageOptimizer::class);
        $this->app->singleton(WebScraper::class);

        $this->app->singleton(ContentRewriter::class, fn($app) => new ContentRewriter($app->make(AiService::class)));
        $this->app->singleton(SeoSuggester::class, fn($app) => new SeoSuggester($app->make(AiService::class)));
        $this->app->singleton(ImageGenerator::class);

        $this->app->alias(PublisherManager::class, 'scribe-ai');
        $this->app->alias(ContentPipeline::class, 'scribe-pipeline');
        $this->app->alias(ContentSourceManager::class, 'scribe-source');
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

            $this->commands([
                ListRunsCommand::class,
                ProcessUrlCommand::class,
                PublishApprovedCommand::class,
                PublishArticleCommand::class,
                ResumeRunCommand::class,
            ]);
        }
    }
}
