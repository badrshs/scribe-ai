<?php

namespace Bader\ContentPublisher\Models;

use Bader\ContentPublisher\Enums\SourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'url',
        'config',
        'active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'config' => 'array',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOfType(Builder $query, SourceType $type): Builder
    {
        return $query->where('type', $type);
    }
}
