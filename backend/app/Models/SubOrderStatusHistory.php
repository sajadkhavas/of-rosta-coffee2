<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SubOrderStatusHistory extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'sub_order_status_history';

    protected $fillable = [
        'sub_order_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'encrypted:array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Sub-order status history is append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Sub-order status history is append-only.');
        });
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
