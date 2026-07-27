<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentIncidentStatus;
use App\Enums\FulfillmentSlaStatus;
use App\Enums\SubOrderStatus;
use App\Models\OrderEvent;
use App\Models\SubOrder;
use App\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FulfillmentSlaMonitorService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function markBreaches(int $limit = 500): int
    {
        $candidateIds = SubOrder::query()
            ->whereNotNull('fulfillment_committed_at')
            ->where('sla_status', '!=', FulfillmentSlaStatus::Breached->value)
            ->whereDoesntHave('fulfillmentIncidents', static fn (Builder $query): Builder => $query
                ->where('status', FulfillmentIncidentStatus::Open->value))
            ->where(static function (Builder $query): void {
                $query->where(static fn (Builder $accepted): Builder => $accepted
                    ->where('status', SubOrderStatus::Accepted->value)
                    ->whereNotNull('preparation_due_at')
                    ->where('preparation_due_at', '<=', now()))
                    ->orWhere(static fn (Builder $handoff): Builder => $handoff
                        ->whereIn('status', [
                            SubOrderStatus::Preparing->value,
                            SubOrderStatus::ReadyToShip->value,
                        ])
                        ->whereNotNull('handoff_due_at')
                        ->where('handoff_due_at', '<=', now()));
            })
            ->orderBy('id')
            ->limit(max(1, min(5_000, $limit)))
            ->pluck('id');

        $marked = 0;
        foreach ($candidateIds as $subOrderId) {
            $changed = DB::transaction(function () use ($subOrderId): bool {
                $subOrder = SubOrder::query()->whereKey($subOrderId)->lockForUpdate()->first();
                if (! $subOrder instanceof SubOrder || ! $this->isCurrentlyBreached($subOrder)) {
                    return false;
                }

                $subOrder->forceFill(['sla_status' => FulfillmentSlaStatus::Breached])->save();
                $occurredAt = now();
                OrderEvent::query()->firstOrCreate(
                    [
                        'order_id' => $subOrder->order_id,
                        'sub_order_id' => $subOrder->id,
                        'event_type' => 'fulfillment.sla_breached',
                    ],
                    [
                        'previous_state' => $subOrder->status->value,
                        'next_state' => $subOrder->status->value,
                        'actor_type' => 'system',
                        'reason_code' => 'fulfillment_sla_breached',
                        'customer_title' => 'آماده‌سازی سفارش با تأخیر روبه‌رو شده است',
                        'customer_description' => 'تیم رستا تأخیر این بخش از سفارش را پیگیری می‌کند.',
                        'internal_metadata' => [
                            'preparation_due_at' => $subOrder->preparation_due_at?->toIso8601String(),
                            'handoff_due_at' => $subOrder->handoff_due_at?->toIso8601String(),
                        ],
                        'occurred_at' => $occurredAt,
                        'created_at' => $occurredAt,
                    ],
                );

                $this->audit->record(
                    'fulfillment.sla_breached',
                    auditable: $subOrder,
                    metadata: [
                        'order_id' => $subOrder->order_id,
                        'sub_order_id' => $subOrder->id,
                        'status' => $subOrder->status->value,
                    ],
                );

                return true;
            }, 3);

            if ($changed) {
                $marked++;
            }
        }

        return $marked;
    }

    private function isCurrentlyBreached(SubOrder $subOrder): bool
    {
        if ($subOrder->sla_status === FulfillmentSlaStatus::Breached) {
            return false;
        }

        $hasOpenIncident = $subOrder->fulfillmentIncidents()
            ->where('status', FulfillmentIncidentStatus::Open->value)
            ->exists();
        if ($hasOpenIncident) {
            return false;
        }

        return match ($subOrder->status) {
            SubOrderStatus::Accepted => $subOrder->preparation_due_at?->isPast() ?? false,
            SubOrderStatus::Preparing,
            SubOrderStatus::ReadyToShip => $subOrder->handoff_due_at?->isPast() ?? false,
            default => false,
        };
    }
}
