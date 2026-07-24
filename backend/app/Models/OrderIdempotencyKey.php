<?php

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $key
 * @property string|null $request_hash
 * @property IdempotencyStatus $status
 * @property string|null $order_id
 * @property string|null $failure_code
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Order|null $order
 */
final class OrderIdempotencyKey extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'key',
        'request_hash',
        'status',
        'order_id',
        'failure_code',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
