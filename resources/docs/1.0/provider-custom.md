# Custom AI Providers

---

- [Overview](#overview)
- [The AiProvider Contract](#contract)
- [Implementing a Provider](#implementing)
- [Registering the Provider](#registering)
- [Using Your Provider](#using)
- [Full Example](#full-example)

<a name="overview"></a>
## Overview

You can add support for any AI service by implementing the `AiProvider` contract and registering it with the `AiProviderManager`. Your provider will be available alongside the built-in providers.

<a name="contract"></a>
## The AiProvider Contract

```php
namespace Bader\ContentPublisher\Contracts;

interface AiProvider
{
    /**
     * Send a chat-completion request.
     *
     * Response MUST contain: ['choices' => [['message' => ['content' => '...']]]]
     */
    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array;

    /**
     * Generate an image from a text prompt. Return raw binary data or null.
     */
    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string;

    /**
     * Whether this provider supports image generation.
     */
    public function supportsImageGeneration(): bool;

    /**
     * Unique name (e.g. 'mistral', 'cohere').
     */
    public function name(): string;
}
```

<a name="implementing"></a>
## Implementing a Provider

```php
<?php

namespace App\Ai;

use Bader\ContentPublisher\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MistralProvider implements AiProvider
{
    protected string $apiKey;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function name(): string
    {
        return 'mistral';
    }

    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])
            ->timeout(180)
            ->post('https://api.mistral.ai/v1/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Mistral API error: " . $response->body());
        }

        // Mistral uses OpenAI-compatible format, so no normalization needed
        return $response->json();
    }

    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string
    {
        return null; // Mistral doesn't support image generation
    }

    public function supportsImageGeneration(): bool
    {
        return false;
    }
}
```

> {primary} The response from `chat()` **must** follow the OpenAI schema: `['choices' => [['message' => ['content' => '...']]]]`. If the API uses a different format, normalize it in your provider (see `ClaudeProvider` or `GeminiProvider` for examples).

<a name="registering"></a>
## Registering the Provider

In your application's service provider:

```php
use Bader\ContentPublisher\Services\Ai\AiProviderManager;

public function register(): void
{
    app(AiProviderManager::class)->extend('mistral', function (array $config) {
        return new \App\Ai\MistralProvider($config);
    });
}
```

Add provider config to `config/scribe-ai.php`:

```php
'providers' => [
    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
    ],
    // ... other providers
],
```

<a name="using"></a>
## Using Your Provider

```dotenv
AI_PROVIDER=mistral
MISTRAL_API_KEY=your-key
OPENAI_CONTENT_MODEL=mistral-large-latest
```

The entire pipeline now uses your custom provider.

<a name="full-example"></a>
## Full Example

A complete image-capable custom provider with response normalization:

```php
public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array
{
    $response = $this->callApi($messages, $model, $maxTokens, $jsonMode);

    // Normalize to OpenAI format
    return [
        'choices' => [
            [
                'message' => [
                    'content' => $response['output']['text'] ?? '',
                    'role' => 'assistant',
                ],
                'finish_reason' => $response['finish_reason'] ?? 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => $response['input_tokens'] ?? 0,
            'completion_tokens' => $response['output_tokens'] ?? 0,
            'total_tokens' => ($response['input_tokens'] ?? 0) + ($response['output_tokens'] ?? 0),
        ],
    ];
}
```
