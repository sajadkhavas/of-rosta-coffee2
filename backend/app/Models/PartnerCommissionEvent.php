<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $event_type
 * @property string $source_id
 * @property string $order_id
 * @property string|null $refund_attempt_id
 * @property string $status
 * @property int $attempts
 * @property CarbonImmutable|null $available_at
 * @property CarbonImmutable|null $claimed_at
 * @property CarbonImmutable|null $processed_at
 * @property string|null $last_error_code
 * @property CarbonImmutable|null $last_error_at
 */
final class PartnerCommissionEvent extends Model
{
    use HasUlids;

    public const EVENT_ORDER_PAID = 'order_paid';

    public const EVENT_REFUND_SUCCEEDED = 'refund_succeeded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
        ];
    }
}
