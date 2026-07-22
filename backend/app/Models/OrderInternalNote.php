<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OrderInternalNote extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'actor_id',
        'body',
    ];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Internal order notes are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Internal order notes are append-only.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
