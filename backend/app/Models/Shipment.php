<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Shipment extends Model
{
    use HasUlids;

    protected $fillable = [
        'sub_order_id',
        'carrier',
        'tracking_code',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
