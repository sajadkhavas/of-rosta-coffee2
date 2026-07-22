<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class MediaAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'alt',
        'width',
        'height',
        'blur_data_url',
        'sources',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'sources' => 'array',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_media')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
