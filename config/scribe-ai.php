<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Publisher Channel
    |--------------------------------------------------------------------------
    |
    | The default channel used when publishing content. Each channel maps
    | to a driver configuration below. Use 'log' for development.
    |
    */

    'default' => env('PUBLISHER_DEFAULT_CHANNEL', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Active Channels
    |--------------------------------------------------------------------------
    |
    | Channels that will be used when calling publishToChannels().
    | Only channels listed here will receive published content.
    |
    */

    'channels' => explode(',', env('PUBLISHER_CHANNELS', 'log')),

    /*
    |--------------------------------------------------------------------------
    | Publisher Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for each publisher driver. Add new drivers by creating
    | a class implementing the Publisher contract and registering it
    | via PublisherManager::extend().
    |
    */

    'drivers' => [

        'log' => [
            'driver' => 'log',
            'level' => 'info',
            'channel' => null,
        ],

        'facebook' => [
            'driver' => 'facebook',
            'page_id' => env('FACEBOOK_PAGE_ID'),
            'access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
            'api_version' => env('FACEBOOK_API_VERSION', 'v21.0'),
            'timeout' => (int) env('FACEBOOK_TIMEOUT', 25),
            'retries' => (int) env('FACEBOOK_RETRIES', 2),
        ],

        'telegram' => [
            'driver' => 'telegram',
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
            'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),
        ],

        'blogger' => [
            'driver' => 'blogger',
            'blog_id' => env('BLOGGER_BLOG_ID'),
            'api_key' => env('BLOGGER_API_KEY'),
            'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        ],

        'wordpress' => [
            'driver' => 'wordpress',
            'url' => env('WORDPRESS_URL'),
            'username' => env('WORDPRESS_USERNAME'),
            'password' => env('WORDPRESS_PASSWORD'),
            'default_status' => env('WORDPRESS_DEFAULT_STATUS', 'publish'),
            'timeout' => (int) env('WORDPRESS_TIMEOUT', 30),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | OpenAI service configuration for content rewriting, SEO suggestions,
    | and image generation. Model fallback ensures resilience.
    |
    */

    'ai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'content_model' => env('OPENAI_CONTENT_MODEL', 'gpt-4o-mini'),
        'fallback_model' => env('OPENAI_FALLBACK_MODEL', 'gpt-4o-mini'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),
        'image_size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
        'image_quality' => env('OPENAI_IMAGE_QUALITY', 'standard'),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4096),

        /*
        | The language the AI should produce the rewritten article in.
        | The system prompt itself is always in English; only the output changes.
        | Examples: 'English', 'Arabic', 'French', 'Spanish', etc.
        */
        'output_language' => env('AI_OUTPUT_LANGUAGE', 'English'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    |
    | Static category map used when no categories exist in the database
    | and none are passed explicitly. Format: [id => name].
    | Leave empty to let the pipeline work without categories.
    |
    | Example:
    |   'categories' => [1 => 'Technology', 2 => 'Health', 3 => 'Business'],
    |
    */

    'categories' => [],

    /*
    |--------------------------------------------------------------------------
    | Content Pipeline
    |--------------------------------------------------------------------------
    |
    | Ordered list of pipeline stages that content flows through.
    | Each stage must implement handle(ContentPayload, Closure): mixed.
    | Add, remove, or reorder stages to customize the processing flow.
    |
    */

    'pipeline' => [
        'stages' => [
            Bader\ContentPublisher\Services\Pipeline\Stages\ScrapeStage::class,
            Bader\ContentPublisher\Services\Pipeline\Stages\AiRewriteStage::class,
            Bader\ContentPublisher\Services\Pipeline\Stages\GenerateImageStage::class,
            Bader\ContentPublisher\Services\Pipeline\Stages\OptimizeImageStage::class,
            Bader\ContentPublisher\Services\Pipeline\Stages\CreateArticleStage::class,
            Bader\ContentPublisher\Services\Pipeline\Stages\PublishStage::class,
        ],

        /*
        | When true, the pipeline halts and rejects the payload if any stage
        | throws an exception. When false, failing stages log a warning and
        | continue to the next stage (legacy behaviour).
        */
        'halt_on_error' => (bool) env('PIPELINE_HALT_ON_ERROR', true),

        /*
        | When true, each pipeline execution is persisted to the
        | `pipeline_runs` table with payload snapshots, enabling
        | resume on failure via `scribe:resume {id}`.
        | Requires the pipeline_runs migration to be published and run.
        | Set to false if you don't need run tracking.
        */
        'track_runs' => (bool) env('PIPELINE_TRACK_RUNS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    |
    | Settings for image optimization and storage.
    |
    */

    'images' => [
        'optimize' => (bool) env('IMAGE_OPTIMIZE', true),
        'max_width' => (int) env('IMAGE_MAX_WIDTH', 1600),
        'quality' => (int) env('IMAGE_QUALITY', 82),
        'format' => 'webp',
        'min_size_for_conversion' => 20480,
        'directory' => 'articles',
        'disk' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Fetching
    |--------------------------------------------------------------------------
    |
    | Settings for content source drivers (XML feeds, web scraping, etc.).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Content Sources
    |--------------------------------------------------------------------------
    |
    | Configuration for content-source drivers. Each driver fetches raw
    | content from a different medium (web page, RSS feed, raw text, etc.).
    |
    | The pipeline auto-detects the right driver from the identifier,
    | or you can force one via --source=rss / $payload->sourceDriver.
    |
    | Register custom drivers via ContentSourceManager::extend().
    |
    */

    'sources' => [
        'default' => env('CONTENT_SOURCE_DRIVER', 'web'),

        'drivers' => [
            'web' => [
                'timeout' => (int) env('WEB_SCRAPER_TIMEOUT', 30),
                'user_agent' => env('WEB_SCRAPER_USER_AGENT', 'Mozilla/5.0 (compatible; ContentBot/1.0)'),
            ],
            'rss' => [
                'timeout' => (int) env('RSS_TIMEOUT', 30),
                'max_items' => (int) env('RSS_MAX_ITEMS', 10),
            ],
            'text' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue names for different job types. Separate queues enable
    | independent scaling and prioritization.
    |
    */

    'queue' => [
        'pipeline' => env('PIPELINE_QUEUE', 'pipeline'),
        'publishing' => env('PUBLISHING_QUEUE', 'publishing'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    |
    | Optional extension modules. Each extension is loaded only when
    | explicitly enabled, keeping the default footprint minimal.
    |
    */

    'extensions' => [

        /*
        |----------------------------------------------------------------------
        | Telegram Approval Extension
        |----------------------------------------------------------------------
        |
        | Enables the RSS → AI analysis → Telegram approval workflow.
        |
        | Phase 1: scribe:rss-review fetches RSS, optionally filters with AI,
        |          and sends each entry to Telegram with ✅/❌ buttons.
        |
        | Phase 2: scribe:telegram-poll (or the webhook) processes decisions.
        |          Approved entries are dispatched through the full pipeline.
        |
        | The bot_token and chat_id default to the Telegram publish driver
        | settings if not overridden here.
        |
        */

        'telegram_approval' => [
            'enabled' => (bool) env('TELEGRAM_APPROVAL_ENABLED', false),
            'bot_token' => env('TELEGRAM_APPROVAL_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_APPROVAL_CHAT_ID'),
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
            'webhook_path' => env('TELEGRAM_WEBHOOK_PATH', 'api/scribe/telegram/webhook'),
        ],

    ],

];
