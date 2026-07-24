<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string|null $sub_order_id
 * @property string|null $actor_id
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $reason
 * @property array<mixed> $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SubOrder|null $subOrder
 * @property-read User|null $actor
 */
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
        self::updating(static function (): never {
            throw new LogicException('Sub-order status history is append-only.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Sub-order status history is append-only.');
        });
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
