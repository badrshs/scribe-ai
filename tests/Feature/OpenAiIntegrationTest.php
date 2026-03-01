<?php

namespace Badr\ScribeAi\Tests\Feature;

use Badr\ScribeAi\Data\ContentPayload;
use Badr\ScribeAi\Models\Category;
use Badr\ScribeAi\Services\Ai\AiService;
use Badr\ScribeAi\Services\Ai\ImageGenerator;
use Badr\ScribeAi\Services\ImageOptimizer;
use Badr\ScribeAi\Services\Pipeline\ContentPipeline;
use Badr\ScribeAi\Services\Pipeline\Stages\AiRewriteStage;
use Badr\ScribeAi\Services\WebScraper;
use Badr\ScribeAi\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration test that hits the real OpenAI API.
 *
 * Run MANUALLY only:
 *   php vendor/bin/phpunit --group=integration
 *
 * Before running, set your API key in .env.testing:
 *   OPENAI_API_KEY=sk-your-temporary-key
 */
#[Group('integration')]
class OpenAiIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load API key from .env.testing or environment
        $envFile = __DIR__ . '/../../.env.testing';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if ($key === 'OPENAI_API_KEY') {
                        config(['scribe-ai.ai.api_key' => $value]);
                    }
                    if ($key === 'OPENAI_CONTENT_MODEL') {
                        config(['scribe-ai.ai.content_model' => $value]);
                    }
                    if ($key === 'AI_OUTPUT_LANGUAGE') {
                        config(['scribe-ai.ai.output_language' => $value]);
                    }
                }
            }
        }

        $apiKey = config('scribe-ai.ai.api_key');

        if (! $apiKey || $apiKey === 'sk-test-fake-key' || $apiKey === 'sk-PUT-YOUR-TEMPORARY-KEY-HERE') {
            $this->markTestSkipped(
                'No real OpenAI API key configured. Set OPENAI_API_KEY in .env.testing to run integration tests.'
            );
        }
    }

    #[Test]
    public function ai_rewrite_stage_returns_valid_json_structure(): void
    {
        // Seed some categories so the prompt includes them
        Category::query()->create(['name' => 'Technology', 'slug' => 'technology']);
        Category::query()->create(['name' => 'Health', 'slug' => 'health']);
        Category::query()->create(['name' => 'Business', 'slug' => 'business']);

        $sampleContent = <<<'HTML'
        <html>
        <body>
            <nav>Home | About | Contact</nav>
            <h1>The Future of Artificial Intelligence in Healthcare</h1>
            <p>Artificial intelligence is rapidly transforming healthcare delivery worldwide.
            From diagnostic imaging to drug discovery, AI systems are showing remarkable capability
            in areas traditionally dominated by human expertise.</p>
            <h2>Key Applications</h2>
            <ul>
                <li>Medical imaging analysis: AI can detect tumors in radiology scans with high accuracy</li>
                <li>Drug discovery: Machine learning models can predict molecular interactions</li>
                <li>Patient monitoring: Wearable devices with AI can predict health events</li>
            </ul>
            <h2>Challenges and Ethical Considerations</h2>
            <p>Despite the promise, several challenges remain. Data privacy is a major concern,
            as AI systems require large datasets of patient information. There are also questions
            about algorithmic bias and the need for regulatory frameworks.</p>
            <p>Healthcare professionals emphasize that AI should augment, not replace, human
            decision-making in clinical settings.</p>
            <footer>Copyright 2025 ExampleSite. All rights reserved. Click here to subscribe.</footer>
        </body>
        </html>
        HTML;

        $payload = new ContentPayload(
            sourceUrl: 'https://example.com/ai-healthcare',
            rawContent: $sampleContent,
        );

        $aiService = app(AiService::class);
        $stage = new AiRewriteStage($aiService);

        // Register a dummy pipeline so reportProgress works
        $pipeline = app(ContentPipeline::class);
        $progressLog = [];
        $pipeline->onProgress(function (string $stage, string $status) use (&$progressLog) {
            $progressLog[] = "{$stage}: {$status}";
        });

        $result = $stage->handle($payload, fn($p) => $p);

        // ── Assert the AI returned a valid, complete response ──
        $this->assertFalse($result->rejected, 'Content should not be rejected: ' . ($result->rejectionReason ?? ''));

        // Title was rewritten
        $this->assertNotNull($result->title, 'AI should return a title');
        $this->assertNotEmpty($result->title);

        // Content was rewritten in HTML
        $this->assertNotNull($result->content, 'AI should return content');
        $this->assertStringContainsString('<', $result->content, 'Content should be HTML');

        // Description exists
        $this->assertNotNull($result->description, 'AI should return a description');

        // Meta fields
        $this->assertNotNull($result->metaTitle, 'AI should return meta_title');
        $this->assertNotNull($result->metaDescription, 'AI should return meta_description');
        $this->assertGreaterThanOrEqual(40, mb_strlen($result->metaDescription), 'meta_description should be 50+ chars');
        $this->assertLessThanOrEqual(170, mb_strlen($result->metaDescription), 'meta_description should be ~160 chars max');

        // Image prompt (always English)
        $this->assertNotNull($result->imagePrompt, 'AI should return an image prompt');
        $this->assertNotEmpty($result->imagePrompt);

        // Category from our list
        $this->assertNotNull($result->categoryId, 'AI should assign a category');
        $this->assertContains($result->categoryId, [1, 2, 3], 'category_id should be from the provided list');

        // Tags
        $this->assertNotEmpty($result->tags, 'AI should return tags');
        $this->assertGreaterThanOrEqual(3, count($result->tags), 'AI should return at least 3 tags');

        // Slug was generated
        $this->assertNotNull($result->slug);

        // Progress was reported
        $this->assertNotEmpty($progressLog);

        // Print a summary for manual inspection
        fwrite(STDERR, "\n\n=== AI Rewrite Integration Test Results ===\n");
        fwrite(STDERR, "Title:           {$result->title}\n");
        fwrite(STDERR, "Description:     {$result->description}\n");
        fwrite(STDERR, "Category ID:     {$result->categoryId}\n");
        fwrite(STDERR, "Tags:            " . implode(', ', $result->tags) . "\n");
        fwrite(STDERR, "Image Prompt:    " . mb_substr($result->imagePrompt, 0, 100) . "...\n");
        fwrite(STDERR, "Meta Title:      {$result->metaTitle}\n");
        fwrite(STDERR, "Meta Desc:       {$result->metaDescription}\n");
        fwrite(STDERR, "Content Length:  " . mb_strlen($result->content) . " chars\n");
        fwrite(STDERR, "Slug:            {$result->slug}\n");
        fwrite(STDERR, "Progress Log:    " . implode(' → ', $progressLog) . "\n");
        fwrite(STDERR, "===========================================\n\n");
    }

    #[Test]
    public function ai_rewrite_works_without_categories(): void
    {
        // No categories at all — should still work
        $sampleContent = <<<'TEXT'
        How to Build a Simple REST API with Node.js

        Building a REST API with Node.js is straightforward. In this guide we will
        use Express.js to create endpoints for a basic CRUD application.

        Step 1: Install Node.js and create a new project with npm init.
        Step 2: Install Express with npm install express.
        Step 3: Create your routes for GET, POST, PUT, DELETE.
        Step 4: Test with Postman or curl.

        Express simplifies routing and middleware management, making it ideal
        for rapid API development.
        TEXT;

        $payload = new ContentPayload(
            sourceUrl: 'https://example.com/nodejs-api',
            rawContent: $sampleContent,
        );

        $pipeline = app(ContentPipeline::class);
        $stage = new AiRewriteStage(app(AiService::class));

        $result = $stage->handle($payload, fn($p) => $p);

        $this->assertFalse($result->rejected, 'Should not reject: ' . ($result->rejectionReason ?? ''));
        $this->assertNotNull($result->title);
        $this->assertNotNull($result->content);

        // category_id should be null since no categories provided
        $this->assertNull($result->categoryId, 'category_id should be null when no categories are provided');

        fwrite(STDERR, "\n=== No-Categories Test ===\n");
        fwrite(STDERR, "Title: {$result->title}\n");
        fwrite(STDERR, "Category: " . ($result->categoryId ?? 'null') . "\n");
        fwrite(STDERR, "===========================\n\n");
    }
}
