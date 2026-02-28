<?php

namespace Bader\ContentPublisher\Models;

use Bader\ContentPublisher\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'channel',
        'external_id',
        'external_url',
        'status',
        'error',
        'metadata',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Record a successful publish.
     */
    public static function logSuccess(int $articleId, string $channel, ?string $externalId = null, ?string $externalUrl = null, array $metadata = []): static
    {
        return static::query()->create([
            'article_id' => $articleId,
            'channel' => $channel,
            'external_id' => $externalId,
            'external_url' => $externalUrl,
            'status' => PublishStatus::Success,
            'metadata' => $metadata,
            'published_at' => now(),
        ]);
    }

    /**
     * Record a failed publish attempt.
     */
    public static function logFailure(int $articleId, string $channel, string $error, array $metadata = []): static
    {
        return static::query()->create([
            'article_id' => $articleId,
            'channel' => $channel,
            'status' => PublishStatus::Failed,
            'error' => $error,
            'metadata' => $metadata,
        ]);
    }
}
