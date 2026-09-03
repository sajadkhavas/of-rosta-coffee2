<?php

namespace App\Services\Growth;

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Order;
use App\Models\PartnerCommissionEntry;
use App\Models\PartnerCommissionEvent;
use App\Models\RefundAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PartnerCommissionEventService
{
    private const RETRY_MINUTES = 5;

    private const STALE_CLAIM_MINUTES = 10;

    public function __construct(
        private readonly PartnerCommissionService $commissions,
    ) {}

    public function recordPaidOrder(Order $order): PartnerCommissionEvent
    {
        return PartnerCommissionEvent::query()->firstOrCreate(
            [
                'event_type' => PartnerCommissionEvent::EVENT_ORDER_PAID,
                'source_id' => $order->id,
            ],
            [
                'order_id' => $order->id,
                'status' => PartnerCommissionEvent::STATUS_PENDING,
                'available_at' => CarbonImmutable::now(),
            ],
        );
    }

    public function recordSuccessfulRefund(RefundAttempt $refund): PartnerCommissionEvent
    {
        if ($refund->order_id === null) {
            throw new ApiDomainException(
                'growth.refund_order_missing',
                'The successful refund is not linked to an order.',
                409,
            );
        }

        return PartnerCommissionEvent::query()->firstOrCreate(
            [
                'event_type' => PartnerCommissionEvent::EVENT_REFUND_SUCCEEDED,
                'source_id' => $refund->id,
            ],
            [
                'order_id' => $refund->order_id,
                'refund_attempt_id' => $refund->id,
                'status' => PartnerCommissionEvent::STATUS_PENDING,
                'available_at' => CarbonImmutable::now(),
            ],
        );
    }

    /**
     * @return array{processed:int,skipped:int,blocked:int}
     */
    public function processDue(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $now = CarbonImmutable::now();
        $staleBefore = $now->subMinutes(self::STALE_CLAIM_MINUTES);

        $dueIds = PartnerCommissionEvent::query()
            ->whereIn('status', [
                PartnerCommissionEvent::STATUS_PENDING,
                PartnerCommissionEvent::STATUS_BLOCKED,
            ])
            ->where(static function (Builder $query) use ($now): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', $now);
            })
            ->orderBy('available_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if (count($dueIds) < $limit) {
            $staleIds = PartnerCommissionEvent::query()
                ->where('status', PartnerCommissionEvent::STATUS_PROCESSING)
                ->whereNotNull('claimed_at')
                ->where('claimed_at', '<=', $staleBefore)
                ->orderBy('claimed_at')
                ->limit($limit - count($dueIds))
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            $dueIds = array_values(array_unique([...$dueIds, ...$staleIds]));
        }

        $result = ['processed' => 0, 'skipped' => 0, 'blocked' => 0];

        foreach ($dueIds as $eventId) {
            $event = $this->claim($eventId, $now, $staleBefore);
            if (! $event instanceof PartnerCommissionEvent) {
                continue;
            }

            $outcome = $this->processClaimed($event);
            $result[$outcome]++;
        }

        return $result;
    }

    private function claim(
        string $eventId,
        CarbonImmutable $now,
        CarbonImmutable $staleBefore,
    ): ?PartnerCommissionEvent {
        return DB::transaction(function () use ($eventId, $now, $staleBefore): ?PartnerCommissionEvent {
            $event = PartnerCommissionEvent::query()->lockForUpdate()->find($eventId);
            if (! $event instanceof PartnerCommissionEvent || ! $this->isDue($event, $now, $staleBefore)) {
                return null;
            }

            $event->forceFill([
                'status' => PartnerCommissionEvent::STATUS_PROCESSING,
                'attempts' => $event->attempts + 1,
                'claimed_at' => $now,
            ])->save();

            return $event;
        });
    }

    private function isDue(
        PartnerCommissionEvent $event,
        CarbonImmutable $now,
        CarbonImmutable $staleBefore,
    ): bool {
        if (in_array($event->status, [
            PartnerCommissionEvent::STATUS_PENDING,
            PartnerCommissionEvent::STATUS_BLOCKED,
        ], true)) {
            return $event->available_at === null || $event->available_at->lessThanOrEqualTo($now);
        }

        return $event->status === PartnerCommissionEvent::STATUS_PROCESSING
            && $event->claimed_at !== null
            && $event->claimed_at->lessThanOrEqualTo($staleBefore);
    }

    /** @return 'processed'|'skipped'|'blocked' */
    private function processClaimed(PartnerCommissionEvent $event): string
    {
        try {
            $entry = match ($event->event_type) {
                PartnerCommissionEvent::EVENT_ORDER_PAID => $this->processPaidOrder($event),
                PartnerCommissionEvent::EVENT_REFUND_SUCCEEDED => $this->processSuccessfulRefund($event),
                default => throw new ApiDomainException(
                    'growth.commission_event_type_invalid',
                    'The partner commission event type is not supported.',
                    409,
                ),
            };

            $status = $entry instanceof PartnerCommissionEntry
                ? PartnerCommissionEvent::STATUS_PROCESSED
                : PartnerCommissionEvent::STATUS_SKIPPED;

            $event->forceFill([
                'status' => $status,
                'processed_at' => CarbonImmutable::now(),
                'available_at' => null,
                'last_error_code' => null,
                'last_error_at' => null,
            ])->save();

            return $status === PartnerCommissionEvent::STATUS_PROCESSED ? 'processed' : 'skipped';
        } catch (ApiDomainException $exception) {
            $this->block($event, $exception->errorCode);

            return 'blocked';
        } catch (Throwable $exception) {
            report($exception);
            $this->block($event, 'growth.commission_event_processing_failed');

            return 'blocked';
        }
    }

    private function processPaidOrder(PartnerCommissionEvent $event): ?PartnerCommissionEntry
    {
        $order = Order::query()->find($event->order_id);
        if (! $order instanceof Order || $order->status !== OrderStatus::Paid || $order->paid_at === null) {
            throw new ApiDomainException(
                'growth.order_not_paid',
                'The order is not in the paid state.',
                409,
            );
        }

        return $this->commissions->accrueForPaidOrder($order);
    }

    private function processSuccessfulRefund(PartnerCommissionEvent $event): ?PartnerCommissionEntry
    {
        if ($event->refund_attempt_id === null) {
            throw new ApiDomainException(
                'growth.refund_event_invalid',
                'The refund event does not reference a refund attempt.',
                409,
            );
        }

        $refund = RefundAttempt::query()->find($event->refund_attempt_id);
        if (! $refund instanceof RefundAttempt || $refund->status !== RefundStatus::Succeeded || $refund->succeeded_at === null) {
            throw new ApiDomainException(
                'growth.refund_not_succeeded',
                'The refund attempt is not in the succeeded state.',
                409,
            );
        }

        $order = $refund->order;
        if (! $order instanceof Order) {
            throw new ApiDomainException(
                'growth.refund_order_missing',
                'The successful refund is not linked to an order.',
                409,
            );
        }

        $accrual = $this->commissions->accrueForPaidOrder($order);
        if (! $accrual instanceof PartnerCommissionEntry) {
            return null;
        }

        return $this->commissions->reverseForSuccessfulRefund($refund);
    }

    private function block(PartnerCommissionEvent $event, string $errorCode): void
    {
        $now = CarbonImmutable::now();

        $event->forceFill([
            'status' => PartnerCommissionEvent::STATUS_BLOCKED,
            'available_at' => $now->addMinutes(self::RETRY_MINUTES),
            'claimed_at' => null,
            'processed_at' => null,
            'last_error_code' => mb_substr($errorCode, 0, 128),
            'last_error_at' => $now,
        ])->save();
    }
}
