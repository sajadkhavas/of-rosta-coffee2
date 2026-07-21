<?php

namespace App\Models;

use App\Enums\ProcessingMethod;
use App\Enums\ProductStatus;
use App\Enums\RoastLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Product extends Model
{
    use HasUlids;

    protected $fillable = ['roastery_id','origin_id','primary_media_id','name','slug','short_description','description','processing_method','roast_level','arabica_percentage','tasting_notes','brewing_suggestions','seo_title','seo_description','status','published_at'];

    protected function casts(): array
    {
        return ['processing_method' => ProcessingMethod::class,'roast_level' => RoastLevel::class,'status' => ProductStatus::class,'arabica_percentage' => 'integer','tasting_notes' => 'array','brewing_suggestions' => 'array','published_at' => 'immutable_datetime'];
    }

    public function roastery(): BelongsTo { return $this->belongsTo(Roastery::class); }
    public function origin(): BelongsTo { return $this->belongsTo(Origin::class); }
    public function primaryImage(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'primary_media_id'); }

    public function gallery(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'product_media')->withPivot('position')->orderByPivot('position');
    }

    public function variants(): HasMany { return $this->hasMany(ProductVariant::class)->orderBy('weight_grams'); }
    public function roastBatches(): HasMany { return $this->hasMany(RoastBatch::class)->orderByDesc('roasted_at'); }

    public function latestRoastBatch(): HasOne
    {
        return $this->hasOne(RoastBatch::class)->ofMany(
            ['roasted_at' => 'max'],
            static fn (Builder $query): Builder => $query->where('is_active', true)->where(static function (Builder $available): void {
                $available->whereNull('available_from')->orWhere('available_from', '<=', now());
            }),
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published->value)
            ->whereNotNull('published_at')
            ->whereHas('roastery', static fn (Builder $roastery): Builder => $roastery->where('status', 'verified')->whereNotNull('verified_at'));
    }
}
