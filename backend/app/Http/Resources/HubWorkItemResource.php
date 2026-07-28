<?php

namespace App\Http\Resources;

use App\Models\HubWorkItem;
use App\Models\HubWorkItemAction;
use App\Models\ShipmentLeg;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HubWorkItem */
final class HubWorkItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->order?->order_number,
            'sub_order_id' => $this->sub_order_id,
            'order_item_service_id' => $this->order_item_service_id,
            'hub' => ['id' => $this->hub_id, 'code' => $this->hub?->code, 'name' => $this->hub?->name],
            'status' => $this->status->value,
            'public_label' => $this->status->publicLabel(),
            'revision' => $this->revision,
            'assigned_operator' => $this->assignedOperator === null ? null : ['id' => $this->assignedOperator->id, 'name' => $this->assignedOperator->name],
            'profile' => $this->orderItemService?->grindingProfile === null ? null : [
                'id' => $this->orderItemService->grindingProfile->id,
                'name' => $this->orderItemService->grindingProfile->public_name,
                'version' => $this->orderItemService->grindingProfile->version,
            ],
            'weight_grams' => (int) ($this->snapshot['weight_grams'] ?? 0),
            'quantity' => (int) ($this->snapshot['quantity'] ?? 0),
            'inbound_leg' => $this->leg($this->inboundShipmentLeg),
            'outbound_leg' => $this->leg($this->outboundShipmentLeg),
            'received_at' => $this->received_at?->toIso8601String(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'grinding_started_at' => $this->grinding_started_at?->toIso8601String(),
            'quality_checked_at' => $this->quality_checked_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'handed_off_at' => $this->handed_off_at?->toIso8601String(),
            'actions' => $this->actions->map(static fn (HubWorkItemAction $action): array => [
                'id' => $action->id,
                'action' => $action->action->value,
                'from_status' => $action->from_status,
                'to_status' => $action->to_status,
                'public_label' => $action->public_label,
                'actor' => $action->actor === null ? null : ['id' => $action->actor->id, 'name' => $action->actor->name],
                'occurred_at' => $action->occurred_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function leg(?ShipmentLeg $leg): ?array
    {
        if ($leg === null) {
            return null;
        }

        return ['id' => $leg->id, 'route_type' => $leg->route_type, 'status' => $leg->status->value, 'tracking_code' => $leg->tracking_code, 'delivered_at' => $leg->delivered_at?->toIso8601String(), 'picked_up_at' => $leg->picked_up_at?->toIso8601String()];
    }
}
