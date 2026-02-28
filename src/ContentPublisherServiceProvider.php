<?php

namespace Bader\ContentPublisher;

use Bader\ContentPublisher\Console\Commands\ProcessUrlCommand;
use Bader\ContentPublisher\Console\Commands\PublishApprovedCommand;
use Bader\ContentPublisher\Console\Commands\PublishArticleCommand;
use Bader\ContentPublisher\Services\Ai\AiService;
use Bader\ContentPublisher\Services\Ai\ContentRewriter;
use Bader\ContentPublisher\Services\Ai\ImageGenerator;
use Bader\ContentPublisher\Services\Ai\SeoSuggester;
use Bader\ContentPublisher\Services\ImageOptimizer;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Bader\ContentPublisher\Services\Publishing\PublisherManager;
use Bader\ContentPublisher\Services\WebScraper;
use Illuminate\Support\ServiceProvider;

class ContentPublisherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/content-publisher.php',
            'content-publisher',
        );

        $this->app->singleton(PublisherManager::class);
        $this->app->singleton(AiService::class);
        $this->app->singleton(ContentPipeline::class);
        $this->app->singleton(ImageOptimizer::class);
        $this->app->singleton(WebScraper::class);

        $this->app->singleton(ContentRewriter::class, fn($app) => new ContentRewriter($app->make(AiService::class)));
        $this->app->singleton(SeoSuggester::class, fn($app) => new SeoSuggester($app->make(AiService::class)));
        $this->app->singleton(ImageGenerator::class);

        $this->app->alias(PublisherManager::class, 'content-publisher');
        $this->app->alias(ContentPipeline::class, 'content-pipeline');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/content-publisher.php' => config_path('content-publisher.php'),
            ], 'content-publisher-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'content-publisher-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->commands([
                PublishApprovedCommand::class,
                ProcessUrlCommand::class,
                PublishArticleCommand::class,
            ]);
        }
    }
}
