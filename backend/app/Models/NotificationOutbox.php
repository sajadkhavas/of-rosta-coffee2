<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $order_id
 * @property string|null $sub_order_id
 * @property string|null $channel
 * @property string|null $destination
 * @property string|null $template_key
 * @property array<mixed> $payload
 * @property NotificationStatus $status
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string|null $deduplication_key
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $processing_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> ready()
 */
final class NotificationOutbox extends Model
{
    use HasUlids;

    protected $table = 'notification_outbox';

    protected $fillable = [
        'user_id',
        'order_id',
        'sub_order_id',
        'channel',
        'destination',
        'template_key',
        'payload',
        'status',
        'provider',
        'provider_message_id',
        'deduplication_key',
        'attempts',
        'last_error',
        'available_at',
        'processing_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'destination' => 'encrypted',
            'payload' => 'encrypted:array',
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'processing_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query
            ->where('status', NotificationStatus::Pending->value)
            ->where('available_at', '<=', now());
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

    /** @return BelongsTo<SubOrder, $this> */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
