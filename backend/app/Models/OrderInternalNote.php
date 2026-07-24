<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string|null $order_id
 * @property string|null $sub_order_id
 * @property string|null $actor_id
 * @property string|null $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read User|null $actor
 */
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
        self::updating(static function (): never {
            throw new LogicException('Internal order notes are append-only.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Internal order notes are append-only.');
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<SubOrder, $this> */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
