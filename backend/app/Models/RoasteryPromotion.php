<?php

namespace App\Models;

use App\Enums\RoasteryPromotionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RoasteryPromotion extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'name',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoasteryPromotionStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
