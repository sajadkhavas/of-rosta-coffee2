<?php

namespace App\Models;

use App\Enums\FulfillmentIncidentResolution;
use App\Enums\FulfillmentIncidentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $order_id
 * @property string $sub_order_id
 * @property string $roastery_id
 * @property string|null $reported_by_id
 * @property string|null $resolved_by_id
 * @property string|null $refund_attempt_id
 * @property FulfillmentIncidentStatus $status
 * @property string $code
 * @property string $severity
 * @property string $description
 * @property FulfillmentIncidentResolution|null $resolution
 * @property string|null $resolution_note
 * @property CarbonImmutable $reported_at
 * @property CarbonImmutable|null $resolved_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read Roastery|null $roastery
 * @property-read User|null $reporter
 * @property-read User|null $resolver
 * @property-read RefundAttempt|null $refundAttempt
 */
final class FulfillmentIncident extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'roastery_id',
        'reported_by_id',
        'resolved_by_id',
        'refund_attempt_id',
        'status',
        'code',
        'severity',
        'description',
        'resolution',
        'resolution_note',
        'reported_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentIncidentStatus::class,
            'resolution' => FulfillmentIncidentResolution::class,
            'reported_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function roastery(): BelongsTo
    {
        return $this->belongsTo(Roastery::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function refundAttempt(): BelongsTo
    {
        return $this->belongsTo(RefundAttempt::class);
    }
}
