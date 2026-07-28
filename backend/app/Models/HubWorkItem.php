<?php

namespace App\Models;

use App\Enums\HubWorkItemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $order_id
 * @property string $sub_order_id
 * @property string $order_item_id
 * @property string $order_item_service_id
 * @property string $hub_id
 * @property string $inbound_shipment_leg_id
 * @property string $outbound_shipment_leg_id
 * @property string|null $assigned_operator_id
 * @property HubWorkItemStatus $status
 * @property int $revision
 * @property array<mixed> $snapshot
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $assigned_at
 * @property CarbonImmutable|null $grinding_started_at
 * @property CarbonImmutable|null $quality_checked_at
 * @property CarbonImmutable|null $packaged_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $handed_off_at
 * @property CarbonImmutable|null $cancelled_at
 * @property-read Order|null $order
 * @property-read SubOrder|null $subOrder
 * @property-read OrderItem|null $orderItem
 * @property-read RostaHub|null $hub
 * @property-read User|null $assignedOperator
 * @property-read OrderItemService|null $orderItemService
 * @property-read ShipmentLeg|null $inboundShipmentLeg
 * @property-read ShipmentLeg|null $outboundShipmentLeg
 * @property-read Collection<int, HubWorkItemAction> $actions
 */
final class HubWorkItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id', 'sub_order_id', 'order_item_id', 'order_item_service_id', 'hub_id',
        'inbound_shipment_leg_id', 'outbound_shipment_leg_id', 'assigned_operator_id',
        'status', 'revision', 'snapshot', 'received_at', 'assigned_at', 'grinding_started_at',
        'quality_checked_at', 'packaged_at', 'ready_at', 'handed_off_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => HubWorkItemStatus::class,
            'revision' => 'integer',
            'snapshot' => 'encrypted:array',
            'received_at' => 'immutable_datetime',
            'assigned_at' => 'immutable_datetime',
            'grinding_started_at' => 'immutable_datetime',
            'quality_checked_at' => 'immutable_datetime',
            'packaged_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'handed_off_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
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

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function orderItemService(): BelongsTo
    {
        return $this->belongsTo(OrderItemService::class);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(RostaHub::class);
    }

    public function assignedOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_operator_id');
    }

    public function inboundShipmentLeg(): BelongsTo
    {
        return $this->belongsTo(ShipmentLeg::class, 'inbound_shipment_leg_id');
    }

    public function outboundShipmentLeg(): BelongsTo
    {
        return $this->belongsTo(ShipmentLeg::class, 'outbound_shipment_leg_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(HubWorkItemAction::class)->orderBy('occurred_at');
    }
}
