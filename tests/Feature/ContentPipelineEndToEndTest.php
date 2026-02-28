<?php

namespace Bader\ContentPublisher\Tests\Feature;

use Bader\ContentPublisher\Data\ContentPayload;
use Bader\ContentPublisher\Models\Article;
use Bader\ContentPublisher\Models\Category;
use Bader\ContentPublisher\Services\Ai\AiService;
use Bader\ContentPublisher\Services\Ai\ImageGenerator;
use Bader\ContentPublisher\Services\ImageOptimizer;
use Bader\ContentPublisher\Services\Pipeline\ContentPipeline;
use Bader\ContentPublisher\Services\WebScraper;
use Bader\ContentPublisher\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;

class ContentPipelineEndToEndTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    protected function seedCategories(): void
    {
        Category::query()->create(['name' => 'Technology', 'slug' => 'technology']);
        Category::query()->create(['name' => 'Health', 'slug' => 'health']);
        Category::query()->create(['name' => 'Business', 'slug' => 'business']);
    }

    /**
     * Bind mocked services into the container so no real HTTP occurs.
     */
    protected function bindMocks(
        string $scrapedHtml = '<html><body><h1>Test Title</h1><p>Test content paragraph.</p></body></html>',
        array $aiJsonResponse = [],
        string $generatedImagePath = 'articles/ai-test-image.png',
        string $optimizedImagePath = 'articles/ai-test-image.webp',
    ): void {
        // ── WebScraper mock ──
        $scraper = Mockery::mock(WebScraper::class);
        $scraper->shouldReceive('scrape')
            ->andReturn($scrapedHtml);
        $this->app->instance(WebScraper::class, $scraper);

        // ── AiService mock (returns structured JSON for completeJson) ──
        $aiResponse = array_merge([
            'status' => 'ok',
            'reject_reasons' => [],
            'title' => 'Rewritten Test Title',
            'text-to-image' => 'A futuristic illustration of technology in daily life',
            'description' => 'A short summary of the rewritten article.',
            'content' => '<div><h2>Rewritten Content</h2><p>This is the AI-rewritten article body.</p></div>',
            'meta_title' => 'Rewritten Test Title — SEO',
            'meta_description' => 'A concise SEO meta description for the rewritten article.',
            'category_id' => 1,
            'tags' => ['technology', 'ai', 'innovation'],
        ], $aiJsonResponse);

        $aiService = Mockery::mock(AiService::class);
        $aiService->shouldReceive('completeJson')
            ->andReturn($aiResponse);
        $this->app->instance(AiService::class, $aiService);

        // ── ImageGenerator mock ──
        $imageGen = Mockery::mock(ImageGenerator::class);
        $imageGen->shouldReceive('generate')
            ->andReturn($generatedImagePath);
        $this->app->instance(ImageGenerator::class, $imageGen);

        // ── ImageOptimizer mock ──
        $optimizer = Mockery::mock(ImageOptimizer::class);
        $optimizer->shouldReceive('optimizeExisting')
            ->andReturn($optimizedImagePath);
        $this->app->instance(ImageOptimizer::class, $optimizer);
    }

    // ──────────────────────────────────────────────────────────
    //  Tests
    // ──────────────────────────────────────────────────────────

    public function test_full_pipeline_scrape_to_publish(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $payload = ContentPayload::fromUrl('https://example.com/article');

        $result = $pipeline->process($payload);

        // Pipeline should not reject
        $this->assertFalse($result->rejected, 'Pipeline should not reject valid content');

        // Article should be created in the database
        $this->assertNotNull($result->article, 'Article should be created');
        $this->assertDatabaseHas('articles', [
            'id' => $result->article->id,
            'title' => 'Rewritten Test Title',
            'slug' => 'rewritten-test-title',
        ]);

        // Image path should be the optimised version
        $this->assertEquals('articles/ai-test-image.webp', $result->imagePath);

        // Tags should be attached
        $this->assertCount(3, $result->article->tags);

        // Publish results should contain the log channel
        $this->assertArrayHasKey('log', $result->publishResults);
        $this->assertTrue($result->publishResults['log']->success);
    }

    public function test_pipeline_rejects_low_quality_content(): void
    {
        $this->seedCategories();
        $this->bindMocks(aiJsonResponse: [
            'status' => 'reject',
            'reject_reasons' => ['Low quality', 'No informational value'],
        ]);

        $pipeline = app(ContentPipeline::class);
        $payload = ContentPayload::fromUrl('https://example.com/spam');

        $result = $pipeline->process($payload);

        $this->assertTrue($result->rejected);
        $this->assertStringContainsString('Low quality', $result->rejectionReason);
        $this->assertNull($result->article, 'No article should be created for rejected content');
    }

    public function test_pipeline_halts_on_image_generation_error(): void
    {
        $this->seedCategories();

        // Override just the ImageGenerator to throw
        $this->bindMocks();
        $imageGen = Mockery::mock(ImageGenerator::class);
        $imageGen->shouldReceive('generate')
            ->andThrow(new \RuntimeException('DALL-E API error: invalid size'));
        $this->app->instance(ImageGenerator::class, $imageGen);

        $this->app['config']->set('scribe-ai.pipeline.halt_on_error', true);

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        $this->assertTrue($result->rejected);
        $this->assertStringContainsString('Image generation failed', $result->rejectionReason);
    }

    public function test_pipeline_continues_on_error_when_halt_disabled(): void
    {
        $this->seedCategories();

        $this->bindMocks();
        $imageGen = Mockery::mock(ImageGenerator::class);
        $imageGen->shouldReceive('generate')
            ->andThrow(new \RuntimeException('DALL-E API error: rate limit'));
        $this->app->instance(ImageGenerator::class, $imageGen);

        $this->app['config']->set('scribe-ai.pipeline.halt_on_error', false);

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        // Pipeline should continue even though image gen failed
        $this->assertFalse($result->rejected);
        $this->assertNotNull($result->article, 'Article should still be created');
        $this->assertNull($result->imagePath, 'Image should be null when generation fails');
    }

    public function test_scrape_stage_skipped_when_raw_content_provided(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        // WebScraper should NOT be called since we provide raw content
        $scraper = Mockery::mock(WebScraper::class);
        $scraper->shouldNotReceive('scrape');
        $this->app->instance(WebScraper::class, $scraper);

        $pipeline = app(ContentPipeline::class);
        $payload = new ContentPayload(
            sourceUrl: 'https://example.com/article',
            rawContent: '<h1>Pre-scraped title</h1><p>Pre-scraped content</p>',
        );

        $result = $pipeline->process($payload);

        $this->assertFalse($result->rejected);
        $this->assertNotNull($result->article);
    }

    public function test_pipeline_progress_callback_fires(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        $stages = [];

        $pipeline = app(ContentPipeline::class);
        $pipeline->onProgress(function (string $stage, string $status) use (&$stages) {
            $stages[] = "{$stage}:{$status}";
        });

        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        // Pipeline should report start for each stage
        $this->assertContains('Pipeline:started', $stages);
        $this->assertContains('Scrape:started', $stages);
        $this->assertContains('AI Rewrite:started', $stages);
        $this->assertContains('Generate Image:started', $stages);
        $this->assertContains('Optimise Image:started', $stages);
        $this->assertContains('Create Article:started', $stages);
        $this->assertContains('Publish:started', $stages);
        $this->assertContains('Pipeline:completed', $stages);
    }

    public function test_progress_callback_cleared_after_process(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        $callCount = 0;

        $pipeline = app(ContentPipeline::class);
        $pipeline->onProgress(function () use (&$callCount) {
            $callCount++;
        });

        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));
        $firstRunCount = $callCount;

        // Second run should NOT fire the callback (it was cleared)
        $pipeline->process(ContentPayload::fromUrl('https://example.com/other'));

        $this->assertEquals($firstRunCount, $callCount, 'Callback should be cleared after first process()');
    }

    public function test_artisan_process_url_sync_command(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        $this->artisan('scribe:process-url', [
            'url' => 'https://example.com/article',
            '--sync' => true,
        ])
            ->assertSuccessful();

        $this->assertDatabaseCount('articles', 1);
    }

    public function test_artisan_process_url_silent_flag(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        $this->artisan('scribe:process-url', [
            'url' => 'https://example.com/article',
            '--sync' => true,
            '--silent' => true,
        ])
            ->assertSuccessful();

        $this->assertDatabaseCount('articles', 1);
    }

    public function test_pipeline_logs_actions(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        Log::shouldReceive('info')->atLeast()->times(5);
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $pipeline = app(ContentPipeline::class);
        $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));
    }

    public function test_publish_results_logged_to_database(): void
    {
        $this->seedCategories();
        $this->bindMocks();

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        // PublishLog should be created by the PublisherManager
        $this->assertDatabaseHas('publish_logs', [
            'article_id' => $result->article->id,
            'channel' => 'log',
        ]);
    }

    public function test_category_assigned_from_ai_response(): void
    {
        $this->seedCategories();
        $this->bindMocks(aiJsonResponse: [
            'category_id' => 2,
        ]);

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/health'));

        $this->assertEquals(2, $result->article->category_id);
    }

    public function test_image_stage_skipped_when_no_prompt(): void
    {
        $this->seedCategories();
        $this->bindMocks(aiJsonResponse: [
            'text-to-image' => null,
        ]);

        // ImageGenerator should NOT be called
        $imageGen = Mockery::mock(ImageGenerator::class);
        $imageGen->shouldNotReceive('generate');
        $this->app->instance(ImageGenerator::class, $imageGen);

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/article'));

        $this->assertFalse($result->rejected);
        $this->assertNull($result->imagePath);
    }

    public function test_pipeline_works_without_categories(): void
    {
        // No categories seeded, no config, no payload categories
        $this->bindMocks(aiJsonResponse: [
            'category_id' => null,
        ]);

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process(ContentPayload::fromUrl('https://example.com/no-cat'));

        $this->assertFalse($result->rejected);
        $this->assertNotNull($result->article);
        $this->assertNull($result->article->category_id);
    }

    public function test_pipeline_uses_payload_categories(): void
    {
        // No DB categories, but categories passed via payload
        $this->bindMocks(aiJsonResponse: [
            'category_id' => 10,
        ]);

        $payload = new ContentPayload(
            sourceUrl: 'https://example.com/with-cats',
            rawContent: '<p>Some article text for processing</p>',
            categories: [10 => 'Science', 20 => 'Art'],
        );

        $pipeline = app(ContentPipeline::class);
        $result = $pipeline->process($payload);

        $this->assertFalse($result->rejected);
        $this->assertEquals(10, $result->article->category_id);
    }
}
