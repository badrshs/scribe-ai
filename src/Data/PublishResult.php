<?php

namespace Badr\ScribeAi\Data;

/**
 * Result of a single publish operation to one channel.
 */
class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $channel,
        public readonly ?string $externalId = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $error = null,
        public readonly array $metadata = [],
    ) {}

    public static function success(string $channel, ?string $externalId = null, ?string $externalUrl = null, array $metadata = []): static
    {
        return new static(
            success: true,
            channel: $channel,
            externalId: $externalId,
            externalUrl: $externalUrl,
            metadata: $metadata,
        );
    }

    public static function failure(string $channel, string $error, array $metadata = []): static
    {
        return new static(
            success: false,
            channel: $channel,
            error: $error,
            metadata: $metadata,
        );
    }
}
