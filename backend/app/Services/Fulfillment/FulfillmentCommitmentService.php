<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentSlaStatus;
use App\Enums\SubOrderAcceptanceStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\SubOrder;
use App\Models\SubOrderStatusHistory;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;

final class FulfillmentCommitmentService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function commitPaidOrder(
        Order $order,
        ?User $actor = null,
        ?Request $request = null,
    ): void {
        $committedAt = now();
        $preparationDueAt = $committedAt->copy()->addHours(
            (int) config('rosta.fulfillment.preparation_sla_hours', 24),
        );
        $handoffDueAt = $committedAt->copy()->addHours(
            (int) config('rosta.fulfillment.handoff_sla_hours', 48),
        );

        $subOrders = SubOrder::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($subOrders as $subOrder) {
            if (
                $subOrder->status === SubOrderStatus::Accepted
                && $subOrder->acceptance_status === SubOrderAcceptanceStatus::Accepted
                && $subOrder->fulfillment_committed_at !== null
            ) {
                continue;
            }

            $from = $subOrder->status;
            $subOrder->forceFill([
                'status' => SubOrderStatus::Accepted,
                'acceptance_status' => SubOrderAcceptanceStatus::Accepted,
                'accepted_at' => $committedAt,
                'fulfillment_committed_at' => $committedAt,
                'preparation_due_at' => $preparationDueAt,
                'handoff_due_at' => $handoffDueAt,
                'sla_status' => FulfillmentSlaStatus::OnTrack,
            ])->save();

            SubOrderStatusHistory::query()->create([
                'sub_order_id' => $subOrder->id,
                'actor_id' => $actor?->id,
                'from_status' => $from->value,
                'to_status' => SubOrderStatus::Accepted->value,
                'reason' => 'payment_verified_contractual_commitment',
                'metadata' => [
                    'acceptance_mode' => 'automatic_contractual',
                    'preparation_due_at' => $preparationDueAt->toIso8601String(),
                    'handoff_due_at' => $handoffDueAt->toIso8601String(),
                ],
                'created_at' => $committedAt,
            ]);

            OrderEvent::query()->create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'event_type' => 'fulfillment.committed',
                'previous_state' => $from->value,
                'next_state' => SubOrderStatus::Accepted->value,
                'actor_type' => 'system',
                'actor_user_id' => $actor?->id,
                'request_id' => $request?->attributes->get('request_id'),
                'reason_code' => 'payment_verified',
                'customer_title' => 'سفارش برای آماده‌سازی قطعی شد',
                'customer_description' => 'روستری طبق قرارداد موظف به آماده‌سازی و تحویل سفارش به حمل است.',
                'internal_metadata' => [
                    'acceptance_mode' => 'automatic_contractual',
                    'preparation_due_at' => $preparationDueAt->toIso8601String(),
                    'handoff_due_at' => $handoffDueAt->toIso8601String(),
                ],
                'occurred_at' => $committedAt,
                'created_at' => $committedAt,
            ]);
        }

        $this->audit->record(
            'fulfillment.contractually_committed',
            actor: $actor,
            auditable: $order,
            metadata: [
                'sub_order_count' => $subOrders->count(),
                'preparation_due_at' => $preparationDueAt->toIso8601String(),
                'handoff_due_at' => $handoffDueAt->toIso8601String(),
            ],
            request: $request,
        );
    }
}
