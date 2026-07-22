<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
