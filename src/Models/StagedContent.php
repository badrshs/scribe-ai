<?php

namespace Badr\ScribeAi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StagedContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'url',
        'published_date',
        'category',
        'source_name',
        'processed_at',
        'approved',
        'approved_at',
        'published',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_date' => 'date',
            'processed_at' => 'datetime',
            'approved' => 'boolean',
            'approved_at' => 'datetime',
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approved', true);
    }

    public function scopeUnpublished(Builder $query): Builder
    {
        return $query->where('published', false);
    }

    public function scopeReadyToPublish(Builder $query): Builder
    {
        return $query->approved()->unpublished();
    }

    /**
     * Bulk insert staged content records, bypassing Eloquent events.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public static function bulkSave(array $records): void
    {
        if (empty($records)) {
            return;
        }

        $now = now();

        $rows = array_map(fn(array $record) => array_merge($record, [
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $records);

        static::query()->insert($rows);
    }

    /**
     * Filter out articles whose URLs already exist in the database.
     *
     * @param  array<int, array<string, mixed>>  $articles
     * @return array<int, array<string, mixed>>
     */
    public static function filterExistingArticles(array $articles): array
    {
        $urls = array_column($articles, 'url');

        $existingUrls = static::query()
            ->whereIn('url', $urls)
            ->pluck('url')
            ->toArray();

        return array_values(
            array_filter($articles, fn(array $a) => ! in_array($a['url'], $existingUrls))
        );
    }

    /**
     * Mark this staged content as published.
     */
    public function markPublished(): void
    {
        $this->update([
            'published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Mark this staged content as approved.
     */
    public function markApproved(): void
    {
        $this->update([
            'approved' => true,
            'approved_at' => now(),
        ]);
    }
}
