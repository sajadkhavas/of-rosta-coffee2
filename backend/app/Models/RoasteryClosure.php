<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RoasteryClosure extends Model
{
    use HasUlids;

    protected $fillable = [
        'roastery_id',
        'starts_at',
        'ends_at',
        'public_reason',
        'blocks_new_orders',
        'created_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocks_new_orders' => 'boolean',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
