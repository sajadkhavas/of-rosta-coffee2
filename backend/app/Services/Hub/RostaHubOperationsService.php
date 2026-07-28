<?php

namespace App\Services\Hub;

use App\Enums\HubWorkItemAction;
use App\Enums\HubWorkItemStatus;
use App\Enums\OrderItemServiceStatus;
use App\Enums\Role;
use App\Enums\ShipmentLegStatus;
use App\Exceptions\ApiDomainException;
use App\Models\HubWorkItem;
use App\Models\HubWorkItemAction as WorkAction;
use App\Models\OrderEvent;
use App\Models\OrderItemService;
use App\Models\ShipmentLeg;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RostaHubOperationsService
{
    public function createForRoute(
        OrderItemService $service,
        ShipmentLeg $inbound,
        ShipmentLeg $outbound,
        int $weightGrams,
        int $quantity,
    ): HubWorkItem {
        if ($service->provider_type !== 'rosta_hub' || $service->provider_hub_id === null) {
            throw new ApiDomainException('hub_operation.invalid_service', 'این سرویس متعلق به هاب رستا نیست.', 409);
        }

        $item = HubWorkItem::query()->firstOrCreate(
            ['order_item_service_id' => $service->id],
            [
                'order_id' => $service->order_id,
                'sub_order_id' => $service->sub_order_id,
                'order_item_id' => $service->order_item_id,
                'hub_id' => $service->provider_hub_id,
                'inbound_shipment_leg_id' => $inbound->id,
                'outbound_shipment_leg_id' => $outbound->id,
                'status' => HubWorkItemStatus::AwaitingInbound,
                'revision' => 0,
                'snapshot' => [
                    'hub_id' => $service->provider_hub_id,
                    'grinding_profile_id' => $service->grinding_profile_id,
                    'weight_grams' => $weightGrams,
                    'quantity' => $quantity,
                    'service_snapshot' => $service->service_snapshot,
                    'inbound_shipment_leg_id' => $inbound->id,
                    'outbound_shipment_leg_id' => $outbound->id,
                ],
            ],
        );

        WorkAction::query()->firstOrCreate(
            ['idempotency_key' => 'hub-work-item-created:'.$service->id],
            [
                'hub_work_item_id' => $item->id,
                'actor_id' => null,
                'action' => HubWorkItemAction::Created,
                'from_status' => null,
                'to_status' => HubWorkItemStatus::AwaitingInbound->value,
                'public_label' => HubWorkItemStatus::AwaitingInbound->publicLabel(),
                'private_evidence' => null,
                'occurred_at' => now(),
                'created_at' => now(),
            ],
        );

        return $this->load($item);
    }

    public function assign(User $administrator, string $workItemId, string $operatorId, string $idempotencyKey, ?string $note, Request $request): HubWorkItem
    {
        return DB::transaction(function () use ($administrator, $workItemId, $operatorId, $idempotencyKey, $note, $request): HubWorkItem {
            $item = $this->locked($workItemId);
            if ($existing = $this->replay($item, $idempotencyKey)) {
                return $existing;
            }
            if (! in_array($item->status, [HubWorkItemStatus::Received, HubWorkItemStatus::Assigned, HubWorkItemStatus::ReworkRequired], true)) {
                throw new ApiDomainException('hub_operation.assignment_not_allowed', 'این کار در وضعیت فعلی قابل تخصیص نیست.', 409);
            }
            $operator = User::query()->lockForUpdate()->findOrFail($operatorId);
            if (! ($operator->hasRole(Role::HubOperator) || $operator->hasRole(Role::HubOperator, 'rosta_hub', $item->hub_id))) {
                throw new ApiDomainException('hub_operation.operator_scope_invalid', 'اپراتور برای این هاب مجاز نیست.', 422);
            }
            $previous = $item->status;
            $next = $previous === HubWorkItemStatus::ReworkRequired ? $previous : HubWorkItemStatus::Assigned;
            $item->forceFill([
                'assigned_operator_id' => $operator->id,
                'status' => $next,
                'assigned_at' => now(),
                'revision' => $item->revision + 1,
            ])->save();
            $this->record($item, $administrator, HubWorkItemAction::Assigned, $previous, $next, $idempotencyKey, [
                'operator_id' => $operator->id,
                'note' => $note,
            ], $request);

            return $this->load($item);
        }, 3);
    }

    /** @param array<string, mixed>|null $evidence */
    public function transition(User $actor, string $workItemId, HubWorkItemAction $action, string $idempotencyKey, ?array $evidence, Request $request): HubWorkItem
    {
        return DB::transaction(function () use ($actor, $workItemId, $action, $idempotencyKey, $evidence, $request): HubWorkItem {
            $item = $this->locked($workItemId);
            if ($existing = $this->replay($item, $idempotencyKey)) {
                return $existing;
            }
            $isAdmin = $actor->hasRole(Role::Administrator);
            $isHubOperator = $actor->hasRole(Role::HubOperator) || $actor->hasRole(Role::HubOperator, 'rosta_hub', $item->hub_id);
            if (! $isAdmin && ! $isHubOperator) {
                throw new ApiDomainException('hub_operation.forbidden', 'اجازه عملیات این هاب را ندارید.', 403);
            }
            if (
                ! $isAdmin
                && $action !== HubWorkItemAction::Received
                && ! hash_equals((string) $item->assigned_operator_id, (string) $actor->getAuthIdentifier())
            ) {
                throw new ApiDomainException('hub_operation.not_assigned', 'این کار به اپراتور دیگری تخصیص یافته است.', 403);
            }
            if ($action === HubWorkItemAction::Cancelled && ! $isAdmin) {
                throw new ApiDomainException('hub_operation.admin_required', 'توقف عملیات فقط توسط مدیر مجاز است.', 403);
            }

            $previous = $item->status;
            $next = $this->nextStatus($previous, $action);
            if ($action === HubWorkItemAction::Received) {
                $this->assertInboundDelivered($item);
            }
            if ($action === HubWorkItemAction::HandedOff) {
                $this->assertOutboundReady($item);
            }
            if (in_array($action, [HubWorkItemAction::GrindingStarted, HubWorkItemAction::ReworkStarted], true) && $item->assigned_operator_id === null) {
                throw new ApiDomainException('hub_operation.operator_required', 'پیش از شروع آسیاب باید اپراتور تخصیص یابد.', 409);
            }

            $changes = ['status' => $next, 'revision' => $item->revision + 1];
            $now = now();
            match ($action) {
                HubWorkItemAction::Received => $changes['received_at'] = $now,
                HubWorkItemAction::GrindingStarted, HubWorkItemAction::ReworkStarted => $changes['grinding_started_at'] = $now,
                HubWorkItemAction::QualitySubmitted, HubWorkItemAction::QualityPassed, HubWorkItemAction::QualityFailed => $changes['quality_checked_at'] = $now,
                HubWorkItemAction::ReadyForOutbound => [$changes['packaged_at'], $changes['ready_at']] = [$now, $now],
                HubWorkItemAction::HandedOff => $changes['handed_off_at'] = $now,
                HubWorkItemAction::Cancelled => $changes['cancelled_at'] = $now,
                default => null,
            };
            $item->forceFill($changes)->save();
            $this->syncServiceAndLeg($item, $action, $now);
            $this->record($item, $actor, $action, $previous, $next, $idempotencyKey, $evidence, $request);

            return $this->load($item);
        }, 3);
    }

    public function backfillMissing(): int
    {
        $created = 0;
        OrderItemService::query()
            ->where('service_type', 'grinding')
            ->where('provider_type', 'rosta_hub')
            ->whereNotNull('provider_hub_id')
            ->whereDoesntHave('hubWorkItem')
            ->with(['orderItem', 'shipmentLegs'])
            ->chunkById(100, function ($services) use (&$created): void {
                foreach ($services as $service) {
                    $inbound = $service->shipmentLegs->firstWhere('route_type', 'roastery_to_rosta_hub');
                    $outbound = $service->shipmentLegs->firstWhere('route_type', 'rosta_hub_to_customer');
                    if (! $inbound instanceof ShipmentLeg || ! $outbound instanceof ShipmentLeg || $service->orderItem === null) {
                        continue;
                    }
                    $weight = (int) ($service->orderItem->variant_snapshot['weight_grams'] ?? 0);
                    $this->createForRoute($service, $inbound, $outbound, $weight, $service->orderItem->quantity);
                    $created++;
                }
            });

        return $created;
    }

    private function nextStatus(HubWorkItemStatus $status, HubWorkItemAction $action): HubWorkItemStatus
    {
        $key = $status->value.':'.$action->value;

        return match ($key) {
            'awaiting_inbound:receive' => HubWorkItemStatus::Received,
            'assigned:start_grinding' => HubWorkItemStatus::Grinding,
            'grinding:submit_quality_check' => HubWorkItemStatus::QualityCheck,
            'quality_check:quality_pass' => HubWorkItemStatus::Packaging,
            'quality_check:quality_fail' => HubWorkItemStatus::ReworkRequired,
            'rework_required:restart_grinding' => HubWorkItemStatus::Grinding,
            'packaging:mark_ready' => HubWorkItemStatus::ReadyForOutbound,
            'ready_for_outbound:handoff' => HubWorkItemStatus::HandedOff,
            default => $action === HubWorkItemAction::Cancelled && ! $status->terminal()
                ? HubWorkItemStatus::Cancelled
                : throw new ApiDomainException('hub_operation.invalid_transition', 'گذار عملیاتی هاب در وضعیت فعلی مجاز نیست.', 409),
        };
    }

    private function assertInboundDelivered(HubWorkItem $item): void
    {
        $leg = ShipmentLeg::query()->lockForUpdate()->findOrFail($item->inbound_shipment_leg_id);
        if ($leg->status !== ShipmentLegStatus::Delivered || $leg->delivered_at === null) {
            throw new ApiDomainException('hub_operation.inbound_not_delivered', 'ورودی هنوز به هاب رستا تحویل نشده است.', 409);
        }
    }

    private function assertOutboundReady(HubWorkItem $item): void
    {
        $leg = ShipmentLeg::query()->lockForUpdate()->findOrFail($item->outbound_shipment_leg_id);
        if (! in_array($leg->status, [ShipmentLegStatus::Planned, ShipmentLegStatus::AwaitingPickup], true)) {
            throw new ApiDomainException('hub_operation.outbound_leg_conflict', 'مرحله خروجی در وضعیت قابل تحویل نیست.', 409);
        }
    }

    private function syncServiceAndLeg(HubWorkItem $item, HubWorkItemAction $action, $now): void
    {
        $service = OrderItemService::query()->lockForUpdate()->findOrFail($item->order_item_service_id);
        $serviceChanges = match ($action) {
            HubWorkItemAction::Received, HubWorkItemAction::Assigned => ['status' => OrderItemServiceStatus::Queued],
            HubWorkItemAction::GrindingStarted, HubWorkItemAction::QualitySubmitted, HubWorkItemAction::QualityPassed, HubWorkItemAction::ReworkStarted => [
                'status' => OrderItemServiceStatus::InProgress,
                'started_at' => $service->started_at ?? $now,
                'failed_at' => null,
                'failure_code' => null,
            ],
            HubWorkItemAction::QualityFailed => ['status' => OrderItemServiceStatus::RequiresReview, 'failure_code' => 'quality_rework_required', 'failed_at' => $now],
            HubWorkItemAction::ReadyForOutbound => ['status' => OrderItemServiceStatus::Completed, 'completed_at' => $now, 'failed_at' => null, 'failure_code' => null],
            HubWorkItemAction::Cancelled => ['status' => OrderItemServiceStatus::Cancelled],
            default => [],
        };
        if ($serviceChanges !== []) {
            $service->forceFill($serviceChanges)->save();
        }
        if ($action === HubWorkItemAction::HandedOff) {
            ShipmentLeg::query()->whereKey($item->outbound_shipment_leg_id)->update([
                'status' => ShipmentLegStatus::PickedUp->value,
                'picked_up_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param array<string, mixed>|null $evidence */
    private function record(HubWorkItem $item, ?User $actor, HubWorkItemAction $action, ?HubWorkItemStatus $from, HubWorkItemStatus $to, string $idempotencyKey, ?array $evidence, Request $request): void
    {
        WorkAction::query()->create([
            'hub_work_item_id' => $item->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'idempotency_key' => $idempotencyKey,
            'public_label' => $to->publicLabel(),
            'private_evidence' => $evidence,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
        OrderEvent::query()->create([
            'order_id' => $item->order_id,
            'sub_order_id' => $item->sub_order_id,
            'order_item_id' => $item->order_item_id,
            'order_item_service_id' => $item->order_item_service_id,
            'shipment_leg_id' => $action === HubWorkItemAction::Received ? $item->inbound_shipment_leg_id : ($action === HubWorkItemAction::HandedOff ? $item->outbound_shipment_leg_id : null),
            'event_type' => 'hub.operation.'.$action->value,
            'previous_state' => $from?->value,
            'next_state' => $to->value,
            'actor_type' => $actor?->hasRole(Role::Administrator) ? 'administrator' : ($actor === null ? 'system' : 'hub_operator'),
            'actor_user_id' => $actor?->id,
            'request_id' => (string) ($request->attributes->get('request_id') ?? $idempotencyKey),
            'customer_title' => $to->publicLabel(),
            'customer_description' => null,
            'internal_metadata' => ['hub_work_item_id' => $item->id, 'hub_id' => $item->hub_id, 'action' => $action->value],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function locked(string $id): HubWorkItem
    {
        return HubWorkItem::query()->lockForUpdate()->findOrFail($id);
    }

    private function replay(HubWorkItem $item, string $idempotencyKey): ?HubWorkItem
    {
        $action = WorkAction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($action === null) {
            return null;
        }
        if ($action->hub_work_item_id !== $item->id) {
            throw new ApiDomainException('hub_operation.idempotency_conflict', 'کلید Idempotency برای عملیات دیگری استفاده شده است.', 409);
        }

        return $this->load($item);
    }

    public function load(HubWorkItem $item): HubWorkItem
    {
        return $item->load(['hub', 'assignedOperator:id,name', 'order:id,order_number', 'subOrder:id,roastery_id,status', 'orderItemService.grindingProfile', 'inboundShipmentLeg', 'outboundShipmentLeg', 'actions.actor:id,name']);
    }
}
