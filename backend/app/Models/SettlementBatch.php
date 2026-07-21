<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SettlementBatch extends Model
{
    use HasUlids;

    protected $fillable = [
        'status',
        'currency',
        'total_amount',
        'period_start',
        'period_end',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'total_amount' => 'integer',
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
