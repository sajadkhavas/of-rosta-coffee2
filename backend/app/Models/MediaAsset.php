<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string|null $roastery_id
 * @property string|null $alt
 * @property int $width
 * @property int $height
 * @property string|null $blur_data_url
 * @property string|null $variant_version
 * @property array<mixed> $sources
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 * @property-read Collection<int, Product> $products
 */
final class MediaAsset extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'alt',
        'width',
        'height',
        'blur_data_url',
        'variant_version',
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

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_media')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
