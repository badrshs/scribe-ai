<?php

namespace Badr\ScribeAi\Contracts;

/**
 * Contract for AI provider drivers.
 *
 * Each provider wraps a different AI service (OpenAI, Anthropic Claude,
 * Google Gemini, Ollama, etc.) behind a unified interface that the
 * package's AiService delegates to.
 *
 * Register custom providers via AiProviderManager::extend():
 *   app(AiProviderManager::class)->extend('mistral', fn($config) => new MistralProvider($config));
 */
interface AiProvider
{
    /**
     * Send a chat-completion request and return the raw response array.
     *
     * The response MUST contain at minimum:
     *   ['choices' => [['message' => ['content' => '...']]]]
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, string $model, int $maxTokens, bool $jsonMode = false): array;

    /**
     * Generate an image from a text prompt and return the raw binary data.
     *
     * Return null if this provider does not support image generation.
     */
    public function generateImage(string $prompt, string $model, string $size, string $quality): ?string;

    /**
     * Whether this provider supports image generation.
     */
    public function supportsImageGeneration(): bool;

    /**
     * Get the unique name of this provider (e.g. 'openai', 'claude').
     */
    public function name(): string;
}
