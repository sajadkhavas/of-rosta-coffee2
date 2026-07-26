<?php

namespace App\Models;

use App\Enums\PackagingFeeMode;
use App\Enums\ProcessingMethod;
use App\Enums\ProductStatus;
use App\Enums\RoastLevel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string|null $roastery_id
 * @property string|null $origin_id
 * @property string|null $primary_media_id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property ProcessingMethod $processing_method
 * @property RoastLevel $roast_level
 * @property int $arabica_percentage
 * @property array<mixed> $tasting_notes
 * @property array<mixed> $brewing_suggestions
 * @property PackagingFeeMode $packaging_fee_mode
 * @property int $packaging_fee_amount
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property ProductStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Roastery|null $roastery
 * @property-read Origin|null $origin
 * @property-read MediaAsset|null $primaryImage
 * @property-read Collection<int, MediaAsset> $gallery
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, RoastBatch> $roastBatches
 * @property-read RoastBatch|null $latestRoastBatch
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> published()
 */
final class Product extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'origin_id',
        'primary_media_id',
        'name',
        'slug',
        'short_description',
        'description',
        'processing_method',
        'roast_level',
        'arabica_percentage',
        'tasting_notes',
        'brewing_suggestions',
        'packaging_fee_mode',
        'packaging_fee_amount',
        'seo_title',
        'seo_description',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'processing_method' => ProcessingMethod::class,
            'roast_level' => RoastLevel::class,
            'status' => ProductStatus::class,
            'arabica_percentage' => 'integer',
            'tasting_notes' => 'array',
            'brewing_suggestions' => 'array',
            'packaging_fee_mode' => PackagingFeeMode::class,
            'packaging_fee_amount' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function packagingFee(): int
    {
        return $this->packaging_fee_mode === PackagingFeeMode::Fixed
            ? $this->packaging_fee_amount
            : 0;
    }

    public function packagingIsFree(): bool
    {
        return $this->packagingFee() === 0;
    }

    /** @return BelongsTo<Roastery, $this> */
    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    /** @return BelongsTo<Origin, $this> */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'primary_media_id');
    }

    /** @return BelongsToMany<MediaAsset, $this> */
    public function gallery(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'product_media')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('weight_grams');
    }

    /** @return HasMany<RoastBatch, $this> */
    public function roastBatches(): HasMany
    {
        return $this->hasMany(RoastBatch::class)->orderByDesc('roasted_at');
    }

    /** @return HasOne<RoastBatch, $this> */
    public function latestRoastBatch(): HasOne
    {
        return $this->hasOne(RoastBatch::class)->ofMany(
            ['roasted_at' => 'max', 'id' => 'max'],
            static fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->where(static function (Builder $available): void {
                    $available->whereNull('available_from')
                        ->orWhere('available_from', '<=', now());
                }),
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Published->value)
            ->whereNotNull('published_at')
            ->whereHas(
                'variants',
                static fn (Builder $variant): Builder => $variant->where('is_active', true),
            )
            ->whereHas(
                'roastery',
                static fn (Builder $roastery): Builder => $roastery
                    ->where('status', 'verified')
                    ->whereNotNull('verified_at'),
            );
    }
}
