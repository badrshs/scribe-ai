<?php

namespace Badr\ScribeAi\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->latest();
    }
}
