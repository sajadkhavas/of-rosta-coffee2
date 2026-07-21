<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SettlementItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'settlement_batch_id',
        'roastery_id',
        'amount',
        'currency',
        'statement_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'statement_snapshot' => 'encrypted:array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id');
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }
}
