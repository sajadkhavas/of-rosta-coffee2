<?php

namespace App\Services\Settlement;

use App\Enums\FulfillmentIncidentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementAllocationStatus;
use App\Enums\ShipmentLegStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\ApiDomainException;
use App\Models\FulfillmentIncident;
use App\Models\OrderEvent;
use App\Models\SettlementAllocation;
use App\Models\SettlementBatchAllocation;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SettlementReleaseService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function releaseEligible(int $limit = 500): int
    {
        $ids = SubOrder::query()
            ->where('status', SubOrderStatus::Delivered->value)
            ->whereNull('settlement_released_at')
            ->whereNull('settlement_hold_code')
            ->whereNotNull('dispute_window_ends_at')
            ->where('dispute_window_ends_at', '<=', now())
            ->orderBy('dispute_window_ends_at')
            ->limit(max(1, min(5_000, $limit)))
            ->pluck('id');

        $released = 0;
        foreach ($ids as $id) {
            $changed = DB::transaction(fn (): bool => $this->releaseLocked((string) $id), 3);
            if ($changed) {
                $released++;
            }
        }

        return $released;
    }

    /** @param array{action: string, code?: string|null, note: string} $input */
    public function setHold(User $actor, SubOrder $subOrder, array $input, Request $request): SubOrder
    {
        return DB::transaction(function () use ($actor, $subOrder, $input, $request): SubOrder {
            $locked = SubOrder::query()->whereKey($subOrder->id)->lockForUpdate()->firstOrFail();
            $action = $input['action'];

            $batched = SettlementBatchAllocation::query()
                ->whereIn('settlement_allocation_id', SettlementAllocation::query()
                    ->where('sub_order_id', $locked->id)
                    ->select('id'))
                ->exists();
            if ($batched) {
                throw new ApiDomainException(
                    'settlement.hold_after_batch_forbidden',
                    'پس از ورود Allocation به Batch، تغییر Hold مجاز نیست.',
                    409,
                );
            }

            if ($action === 'hold') {
                $code = trim((string) ($input['code'] ?? ''));
                if ($code === '') {
                    throw new ApiDomainException('settlement.hold_code_required', 'کد توقف تسویه الزامی است.', 422);
                }
                $locked->forceFill([
                    'settlement_hold_code' => $code,
                    'settlement_hold_note' => trim($input['note']),
                    'settlement_released_at' => null,
                ])->save();
                SettlementAllocation::query()
                    ->where('sub_order_id', $locked->id)
                    ->where('owner_type', 'roastery')
                    ->where('status', SettlementAllocationStatus::Eligible->value)
                    ->update([
                        'status' => SettlementAllocationStatus::Held->value,
                        'eligible_at' => null,
                    ]);
            } else {
                $locked->forceFill([
                    'settlement_hold_code' => null,
                    'settlement_hold_note' => null,
                ])->save();
            }

            $this->audit->record(
                $action === 'hold' ? 'settlement.hold_set' : 'settlement.hold_released',
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'sub_order_id' => $locked->id,
                    'code' => $locked->settlement_hold_code,
                    'note' => trim($input['note']),
                ],
                request: $request,
            );

            return $locked->refresh();
        }, 3);
    }

    private function releaseLocked(string $subOrderId): bool
    {
        $subOrder = SubOrder::query()->whereKey($subOrderId)->lockForUpdate()->first();
        if (! $subOrder instanceof SubOrder || ! $this->canRelease($subOrder)) {
            return false;
        }

        $allocations = SettlementAllocation::query()
            ->where('sub_order_id', $subOrder->id)
            ->where('owner_type', 'roastery')
            ->where('owner_id', $subOrder->roastery_id)
            ->where('status', SettlementAllocationStatus::Held->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $releasedAt = now();
        foreach ($allocations as $allocation) {
            $allocation->forceFill([
                'status' => SettlementAllocationStatus::Eligible,
                'eligible_at' => $releasedAt,
            ])->save();
        }
        $subOrder->forceFill(['settlement_released_at' => $releasedAt])->save();

        OrderEvent::query()->create([
            'order_id' => $subOrder->order_id,
            'sub_order_id' => $subOrder->id,
            'event_type' => 'settlement.allocations_released',
            'previous_state' => SettlementAllocationStatus::Held->value,
            'next_state' => SettlementAllocationStatus::Eligible->value,
            'actor_type' => 'system',
            'reason_code' => 'dispute_window_elapsed',
            'customer_title' => 'مهلت اعلام مشکل پایان یافت',
            'customer_description' => 'فرآیند مالی این بخش از سفارش ادامه پیدا کرده است.',
            'internal_metadata' => [
                'allocation_count' => $allocations->count(),
                'net_total' => $allocations->sum('net_amount'),
            ],
            'occurred_at' => $releasedAt,
            'created_at' => $releasedAt,
        ]);
        $this->audit->record(
            'settlement.allocations_released',
            auditable: $subOrder,
            metadata: [
                'sub_order_id' => $subOrder->id,
                'roastery_id' => $subOrder->roastery_id,
                'allocation_count' => $allocations->count(),
                'net_total' => $allocations->sum('net_amount'),
            ],
        );

        return true;
    }

    private function canRelease(SubOrder $subOrder): bool
    {
        if (
            $subOrder->status !== SubOrderStatus::Delivered
            || $subOrder->settlement_released_at !== null
            || $subOrder->settlement_hold_code !== null
            || $subOrder->dispute_window_ends_at === null
            || $subOrder->dispute_window_ends_at->isFuture()
        ) {
            return false;
        }

        if (FulfillmentIncident::query()
            ->where('sub_order_id', $subOrder->id)
            ->where('status', FulfillmentIncidentStatus::Open->value)
            ->exists()) {
            return false;
        }

        if (FulfillmentIncident::query()
            ->where('sub_order_id', $subOrder->id)
            ->whereHas('refundAttempt', static fn ($query) => $query->whereIn('status', [
                RefundStatus::Requested->value,
                RefundStatus::Approved->value,
                RefundStatus::Processing->value,
                RefundStatus::Succeeded->value,
                RefundStatus::RequiresReview->value,
            ]))
            ->exists()) {
            return false;
        }

        return ! $subOrder->shipmentLegs()
            ->whereIn('status', [
                ShipmentLegStatus::Lost->value,
                ShipmentLegStatus::Damaged->value,
                ShipmentLegStatus::RequiresReview->value,
                ShipmentLegStatus::DeliveryFailed->value,
            ])
            ->exists();
    }
}
