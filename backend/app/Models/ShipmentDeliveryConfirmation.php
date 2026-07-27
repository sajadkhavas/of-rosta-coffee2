<?php

namespace App\Models;

use App\Enums\DeliveryConfirmationSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $shipment_leg_id
 * @property string $order_id
 * @property string $sub_order_id
 * @property string|null $confirmed_by_id
 * @property DeliveryConfirmationSource $source
 * @property string $idempotency_key
 * @property string $proof_type
 * @property array<mixed>|null $proof_payload
 * @property string|null $customer_note
 * @property CarbonImmutable $confirmed_at
 * @property-read ShipmentLeg|null $shipmentLeg
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read User|null $confirmedBy
 */
final class ShipmentDeliveryConfirmation extends Model
{
    use HasUlids;

    protected $fillable = [
        'shipment_leg_id',
        'order_id',
        'sub_order_id',
        'confirmed_by_id',
        'source',
        'idempotency_key',
        'proof_type',
        'proof_payload',
        'customer_note',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => DeliveryConfirmationSource::class,
            'proof_payload' => 'encrypted:array',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function shipmentLeg(): BelongsTo
    {
        return $this->belongsTo(ShipmentLeg::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }
}
