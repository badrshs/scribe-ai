# Testing

---

- [Overview](#overview)
- [Test Environment](#test-environment)
- [Running The Suite](#running-suite)
- [Writing Tests](#writing-tests)
- [Mocking AI Providers](#mocking-providers)
- [Testing Events](#testing-events)
- [Testing Pipeline](#testing-pipeline)
- [Testing Publishers](#testing-publishers)
- [CI Integration](#ci)

<a name="overview"></a>
## Overview

Scribe AI ships with a comprehensive test suite built on [Orchestra Testbench](https://github.com/orchestral/testbench) and PHPUnit. The suite covers AI providers, the content pipeline, events, publishers, and extensions.

Current coverage: **53 Feature tests, 132 assertions**.

<a name="test-environment"></a>
## Test Environment

The package's `TestCase` base class extends `Orchestra\Testbench\TestCase` and automatically:

- Registers `ScribeAiServiceProvider`
- Loads package migrations
- Sets `scribe-ai.tracking.enabled` to `false` (avoids database writes for run tracking in tests)
- Uses an in-memory SQLite database

```php
use Badr\ScribeAi\Tests\TestCase;

class MyTest extends TestCase
{
    // TestCase is pre-configured — just write test methods
}
```

<a name="running-suite"></a>
## Running The Suite

```bash
# Run all tests
./vendor/bin/phpunit

# Run a specific test file
./vendor/bin/phpunit tests/Feature/AiProviderManagerTest.php

# Run a specific test method
./vendor/bin/phpunit --filter="test_openai_provider_chat"

# With coverage (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-html=coverage
```

<a name="writing-tests"></a>
## Writing Tests

### Basic Test Structure

```php
<?php

namespace Badr\ScribeAi\Tests\Feature;

use Badr\ScribeAi\Tests\TestCase;

class MyFeatureTest extends TestCase
{
    public function test_something_works(): void
    {
        // Arrange
        config(['scribe-ai.some_key' => 'value']);

        // Act
        $result = app(SomeService::class)->doWork();

        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Disabling Run Tracking

When testing the pipeline directly, disable run tracking to avoid requiring the `pipeline_runs` table:

```php
use Badr\ScribeAi\Services\Pipeline\ContentPipeline;

$pipeline = app(ContentPipeline::class)->withoutTracking();
$result = $pipeline->process($payload);
```

<a name="mocking-providers"></a>
## Mocking AI Providers

### Mock a Built-in Provider

```php
use Badr\ScribeAi\Contracts\AiProvider;
use Badr\ScribeAi\Services\Ai\AiProviderManager;

public function test_pipeline_with_mock_ai(): void
{
    $mock = $this->createMock(AiProvider::class);
    $mock->method('chat')->willReturn([
        'choices' => [['message' => ['content' => '{"title":"Test","body":"Content"}']]],
    ]);
    $mock->method('name')->willReturn('mock');
    $mock->method('supportsImageGeneration')->willReturn(false);

    $manager = app(AiProviderManager::class);
    $manager->extend('mock', fn() => $mock);

    config(['scribe-ai.default_provider' => 'mock']);

    // Now the pipeline will use your mock provider
}
```

### Register a Custom Test Provider

```php
$manager->extend('test', fn() => new class implements AiProvider {
    public function chat(array $messages, array $options = []): array
    {
        return ['choices' => [['message' => ['content' => 'mocked response']]]];
    }

    public function generateImage(string $prompt, array $options = []): string
    {
        return '/tmp/test-image.png';
    }

    public function supportsImageGeneration(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'test';
    }
});
```

<a name="testing-events"></a>
## Testing Events

Use Laravel's `Event::fake()` to assert events are dispatched:

```php
use Illuminate\Support\Facades\Event;
use Badr\ScribeAi\Events\PipelineStarted;
use Badr\ScribeAi\Events\PipelineCompleted;
use Badr\ScribeAi\Events\ContentScraped;

public function test_pipeline_dispatches_events(): void
{
    Event::fake();

    // Run the pipeline...

    Event::assertDispatched(PipelineStarted::class);
    Event::assertDispatched(ContentScraped::class, function ($event) {
        return $event->payload->sourceUrl === 'https://example.com';
    });
    Event::assertDispatched(PipelineCompleted::class);
}
```

### Assert Events Are NOT Dispatched

```php
public function test_skipped_stage_does_not_fire_event(): void
{
    Event::fake();

    // Process a payload that already has rawContent (ScrapeStage skips)
    $payload = ContentPayload::fromUrl('https://example.com')->with([
        'rawContent' => 'Pre-scraped content',
    ]);

    // Run pipeline...

    Event::assertNotDispatched(ContentScraped::class);
}
```

<a name="testing-pipeline"></a>
## Testing Pipeline Stages

### Test a Single Stage

```php
use Badr\ScribeAi\Data\ContentPayload;
use Badr\ScribeAi\Services\Pipeline\Stages\ScrapeStage;

public function test_scrape_stage_extracts_content(): void
{
    $stage = app(ScrapeStage::class);
    $payload = ContentPayload::fromUrl('https://example.com');

    $result = $stage->handle($payload, fn($p) => $p);

    $this->assertNotNull($result->rawContent);
    $this->assertNotEmpty($result->title);
}
```

### Test Custom Stage Order

```php
$pipeline = app(ContentPipeline::class)
    ->withoutTracking()
    ->through([
        MyCustomStage::class,
        CreateArticleStage::class,
    ]);

$result = $pipeline->process($payload);
```

<a name="testing-publishers"></a>
## Testing Publishers

### Use the Log Driver

The `log` driver never makes external HTTP calls — ideal for tests:

```php
config(['scribe-ai.channels' => ['log']]);

$manager = app(PublisherManager::class);
$results = $manager->publish($article);

$this->assertTrue($results[0]->success);
$this->assertEquals('log', $results[0]->channel);
```

### Mock a Driver

```php
use Badr\ScribeAi\Contracts\Publisher;

$mock = $this->createMock(Publisher::class);
$mock->method('publish')->willReturn(new PublishResult(
    channel: 'mock',
    success: true,
    externalId: 'ext-123',
));

app(PublisherManager::class)->extend('mock', fn() => $mock);
config(['scribe-ai.channels' => ['mock']]);
```

<a name="ci"></a>
## CI Integration

The package includes a GitHub Actions workflow at `.github/workflows/tests.yml`:

```yaml
strategy:
  matrix:
    php: [8.2, 8.3, 8.4]

steps:
  - uses: actions/checkout@v4
  - uses: shivammathur/setup-php@v2
    with:
      php-version: ${{ matrix.php }}
      extensions: gd, mbstring, sqlite3
  - run: composer install --no-interaction --prefer-dist
  - run: vendor/bin/phpunit
```

Tests run on PHP 8.2, 8.3, and 8.4 on every push and pull request.

> {primary} When adding new features, always add corresponding tests. Place Feature tests in `tests/Feature/` and follow the naming convention `{Feature}Test.php`.
