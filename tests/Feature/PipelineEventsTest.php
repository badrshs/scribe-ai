<?php

namespace Bader\ContentPublisher\Tests\Feature;

use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Events\ArticleCreated;
use Bader\ContentPublisher\Events\ArticlePublished;
use Bader\ContentPublisher\Events\ContentRewritten;
use Bader\ContentPublisher\Events\ContentScraped;
use Bader\ContentPublisher\Events\ImageGenerated;
use Bader\ContentPublisher\Events\ImageOptimized;
use Bader\ContentPublisher\Events\PipelineCompleted;
use Bader\ContentPublisher\Events\PipelineFailed;
use Bader\ContentPublisher\Events\PipelineStarted;
use Bader\ContentPublisher\Models\Category;
use Bader\ContentPublisher\Services\Ai\AiService;
use Bader\ContentPublisher\Services\Ai\ImageGenerator;
use Bader\ContentPublisher\Services\ImageOptimizer;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Bader\ContentPublisher\Services\WebScraper;
use Bader\ContentPublisher\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Mockery;

class PipelineEventsTest extends TestCase
{
    protected function seedCategories(): void
    {
        Category::query()->create(['name' => 'Technology', 'slug' => 'technology']);
    }

    protected function bindMocks(array $aiOverrides = []): void
    {
        $scraper = Mockery::mock(WebScraper::class);
        $scraper->shouldReceive('scrape')
            ->andReturn('<html><body><h1>Test</h1><p>Content</p></body></html>');
        $this->app->instance(WebScraper::class, $scraper);

        $aiResponse = array_merge([
            'status' => 'ok',
            'title' => 'Test Article',
            'text-to-image' => 'A test image prompt',
            'description' => 'Test description.',
            'content' => '<p>Rewritten content</p>',
            'meta_title' => 'Test — SEO',
            'meta_description' => 'Meta description.',
            'category_id' => 1,
            'tags' => ['test'],
        ], $aiOverrides);

        $aiService = Mockery::mock(AiService::class);
        $aiService->shouldReceive('completeJson')->andReturn($aiResponse);
        $this->app->instance(AiService::class, $aiService);

        $imageGen = Mockery::mock(ImageGenerator::class);
        $imageGen->shouldReceive('generate')->andReturn('articles/test.png');
        $this->app->instance(ImageGenerator::class, $imageGen);

        $optimizer = Mockery::mock(ImageOptimizer::class);
        $optimizer->shouldReceive('optimizeExisting')->andReturn('articles/test.webp');
        $this->app->instance(ImageOptimizer::class, $optimizer);
    }

    public function test_pipeline_started_event_fired(): void
    {
        Event::fake([PipelineStarted::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(PipelineStarted::class, function (PipelineStarted $event) {
            $this->assertSame('https://example.com/article', $event->payload->sourceUrl);

            return true;
        });
    }

    public function test_pipeline_completed_event_fired(): void
    {
        Event::fake([PipelineCompleted::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(PipelineCompleted::class);
    }

    public function test_pipeline_failed_event_on_rejection(): void
    {
        Event::fake([PipelineFailed::class]);
        $this->seedCategories();
        $this->bindMocks(['status' => 'reject', 'reject_reasons' => ['duplicate']]);

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(PipelineFailed::class, function (PipelineFailed $event) {
            $this->assertStringContainsString('duplicate', $event->reason);

            return true;
        });
    }

    public function test_pipeline_failed_event_on_exception(): void
    {
        Event::fake([PipelineFailed::class]);
        $this->seedCategories();

        // Make the scraper throw
        $scraper = Mockery::mock(WebScraper::class);
        $scraper->shouldReceive('scrape')->andThrow(new \RuntimeException('Connection refused'));
        $this->app->instance(WebScraper::class, $scraper);

        // Still need AI mocks even if they don't get called
        $this->bindMocks();

        // Re-bind the throwing scraper (bindMocks replaced it)
        $this->app->instance(WebScraper::class, $scraper);

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        // halt_on_error is true, so pipeline should fail
        Event::assertDispatched(PipelineFailed::class);
    }

    public function test_content_scraped_event_fired(): void
    {
        Event::fake([ContentScraped::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(ContentScraped::class, function (ContentScraped $event) {
            $this->assertGreaterThan(0, $event->contentLength);
            $this->assertNotEmpty($event->driver);

            return true;
        });
    }

    public function test_content_rewritten_event_fired(): void
    {
        Event::fake([ContentRewritten::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(ContentRewritten::class, function (ContentRewritten $event) {
            $this->assertSame('Test Article', $event->title);
            $this->assertSame(1, $event->categoryId);

            return true;
        });
    }

    public function test_image_generated_event_fired(): void
    {
        Event::fake([ImageGenerated::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(ImageGenerated::class, function (ImageGenerated $event) {
            $this->assertSame('articles/test.png', $event->imagePath);

            return true;
        });
    }

    public function test_image_optimized_event_fired(): void
    {
        Event::fake([ImageOptimized::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(ImageOptimized::class, function (ImageOptimized $event) {
            $this->assertSame('articles/test.png', $event->originalPath);
            $this->assertSame('articles/test.webp', $event->optimizedPath);

            return true;
        });
    }

    public function test_article_created_event_fired(): void
    {
        Event::fake([ArticleCreated::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(ArticleCreated::class, function (ArticleCreated $event) {
            $this->assertNotNull($event->article);
            $this->assertSame('Test Article', $event->article->title);

            return true;
        });
    }

    public function test_article_published_event_fired_per_channel(): void
    {
        Event::fake([ArticlePublished::class]);
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(ArticlePublished::class, function (ArticlePublished $event) {
            $this->assertSame('log', $event->channel);
            $this->assertTrue($event->result->success);

            return true;
        });
    }

    public function test_all_events_fired_in_successful_pipeline(): void
    {
        Event::fake([
            PipelineStarted::class,
            ContentScraped::class,
            ContentRewritten::class,
            ImageGenerated::class,
            ImageOptimized::class,
            ArticleCreated::class,
            ArticlePublished::class,
            PipelineCompleted::class,
        ]);

        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertDispatched(PipelineStarted::class);
        Event::assertDispatched(ContentScraped::class);
        Event::assertDispatched(ContentRewritten::class);
        Event::assertDispatched(ImageGenerated::class);
        Event::assertDispatched(ImageOptimized::class);
        Event::assertDispatched(ArticleCreated::class);
        Event::assertDispatched(ArticlePublished::class);
        Event::assertDispatched(PipelineCompleted::class);
        Event::assertNotDispatched(PipelineFailed::class);
    }

    public function test_scrape_skipped_does_not_fire_event(): void
    {
        Event::fake([ContentScraped::class]);
        $this->seedCategories();
        $this->bindMocks();

        // Provide rawContent so scrape is skipped
        $payload = new ContentPayload(
            sourceUrl: 'https://example.com',
            rawContent: 'Pre-scraped content',
            cleanedContent: 'Pre-scraped content',
        );

        $pipeline = app(ContentPipeline::class);
        $pipeline->process($payload);

        Event::assertNotDispatched(ContentScraped::class);
    }

    public function test_image_event_not_fired_when_no_prompt(): void
    {
        Event::fake([ImageGenerated::class]);
        $this->seedCategories();
        $this->bindMocks(['text-to-image' => null, 'image_prompt' => null]);

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        Event::assertNotDispatched(ImageGenerated::class);
    }
}
