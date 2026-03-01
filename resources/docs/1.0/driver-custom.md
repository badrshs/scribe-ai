# Custom Publisher Drivers

---

- [Overview](#overview)
- [The Publisher Contract](#contract)
- [Implementing a Driver](#implementing)
- [Registering the Driver](#registering)
- [PublishResult](#publish-result)

<a name="overview"></a>
## Overview

Add support for any publishing platform by implementing the `Publisher` contract and registering it with the `PublisherManager`.

<a name="contract"></a>
## The Publisher Contract

```php
namespace Badr\ScribeAi\Contracts;

use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;

interface Publisher
{
    /**
     * Publish an article to this channel.
     */
    public function publish(Article $article, array $options = []): PublishResult;

    /**
     * Whether the driver supports publishing the given article.
     */
    public function supports(Article $article): bool;

    /**
     * Get the channel name.
     */
    public function channel(): string;
}
```

<a name="implementing"></a>
## Implementing a Driver

**Example: Slack driver**

```php
<?php

namespace App\Publishers;

use Badr\ScribeAi\Contracts\Publisher;
use Badr\ScribeAi\Data\PublishResult;
use Badr\ScribeAi\Models\Article;
use Illuminate\Support\Facades\Http;

class SlackDriver implements Publisher
{
    public function __construct(protected array $config = []) {}

    public function publish(Article $article, array $options = []): PublishResult
    {
        $webhookUrl = $this->config['webhook_url']
            ?? throw new \RuntimeException('Slack webhook_url not configured');

        $response = Http::post($webhookUrl, [
            'text' => "*{$article->title}*\n{$article->description}",
            'unfurl_links' => true,
        ]);

        if ($response->failed()) {
            return PublishResult::failure(
                channel: $this->channel(),
                error: "Slack API error: " . $response->body(),
            );
        }

        return PublishResult::success(
            channel: $this->channel(),
            externalId: 'slack-' . $article->id,
            metadata: ['webhook' => 'incoming'],
        );
    }

    public function supports(Article $article): bool
    {
        return $article->isPublished();
    }

    public function channel(): string
    {
        return 'slack';
    }
}
```

<a name="registering"></a>
## Registering the Driver

In your application's service provider:

```php
use Badr\ScribeAi\Services\Publishing\PublisherManager;

public function register(): void
{
    app(PublisherManager::class)->extend('slack', function (array $config) {
        return new \App\Publishers\SlackDriver($config);
    });
}
```

Add driver config to `config/scribe-ai.php`:

```php
'drivers' => [
    'slack' => [
        'driver'      => 'slack',
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],
    // ... other drivers
],
```

**Activate the channel:**

```dotenv
PUBLISHER_CHANNELS=slack,telegram
```

<a name="publish-result"></a>
## PublishResult

All publish methods must return a `PublishResult` DTO.

**Success:**

```php
PublishResult::success(
    channel: 'slack',
    externalId: 'msg-123',         // Platform-specific ID
    externalUrl: 'https://...',    // Public URL (optional)
    metadata: ['key' => 'value'],  // Additional data (optional)
);
```

**Failure:**

```php
PublishResult::failure(
    channel: 'slack',
    error: 'Webhook returned 403',
);
```

The `PublisherManager` automatically logs results to the `publish_logs` table.
