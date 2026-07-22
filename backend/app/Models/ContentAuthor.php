<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContentAuthor extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'bio',
        'credentials',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ContentEntry::class, 'author_id');
    }
}
