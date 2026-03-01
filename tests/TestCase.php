<?php

namespace Badr\ScribeAi\Tests;

use Badr\ScribeAi\ScribeAiServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ScribeAiServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('scribe-ai.ai.api_key', 'sk-test-fake-key');
        $app['config']->set('scribe-ai.ai.output_language', 'English');
        $app['config']->set('scribe-ai.channels', ['log']);
        $app['config']->set('scribe-ai.default', 'log');
        $app['config']->set('scribe-ai.pipeline.halt_on_error', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
