<?php

namespace App\Services\Carrier;

use App\Contracts\CarrierProvider;
use App\Enums\CarrierEventType;
use App\Enums\DeliveryConfirmationSource;
use App\Enums\ShipmentLegStatus;
use App\Exceptions\ApiDomainException;
use App\Models\CarrierWebhookReceipt;
use App\Models\OrderEvent;
use App\Models\Shipment;
use App\Models\ShipmentLeg;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Fulfillment\DeliveryConfirmationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CarrierOperationsService
{
    public function __construct(
        private readonly CarrierProvider $provider,
        private readonly DeliveryConfirmationService $deliveries,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param array{carrier:string,tracking_code:string,status:string,reason:string} $input */
    public function manage(User $actor, ShipmentLeg $leg, array $input, Request $request): ShipmentLeg
    {
        return DB::transaction(function () use ($actor, $leg, $input, $request): ShipmentLeg {
            $locked = ShipmentLeg::query()->whereKey($leg->id)->lockForUpdate()->firstOrFail();
            $assignment = $this->provider->normalizeAssignment($input['carrier'], $input['tracking_code']);
            $target = ShipmentLegStatus::from($input['status']);
            $reason = trim($input['reason']);

            if ($target === ShipmentLegStatus::Delivered) {
                throw new ApiDomainException(
                    'carrier.delivery_confirmation_required',
                    'تحویل نهایی فقط از مسیر ثبت مدرک تحویل انجام می‌شود.',
                    409,
                );
            }

            $this->applyTransition(
                $locked,
                $target,
                $assignment['carrier'],
                $assignment['tracking_code'],
                $reason,
                now()->toImmutable(),
                'administrator',
                $actor,
                $request,
                false,
            );

            $this->audit->record(
                'carrier.shipment_leg_managed',
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'provider' => $this->provider->key(),
                    'automated_dispatch' => $this->provider->supportsAutomatedDispatch(),
                    'carrier' => $assignment['carrier'],
                    'status' => $locked->status->value,
                    'reason' => mb_substr($reason, 0, 500),
                ],
                request: $request,
            );

            return $locked->refresh();
        }, 3);
    }

    /**
     * @param  array{carrier:string,tracking_code:string,event_type:string,occurred_at:string,evidence_reference?:string|null}  $input
     * @return array{receipt:CarrierWebhookReceipt,shipment_leg:ShipmentLeg,replayed:bool}
     */
    public function ingest(array $input, string $eventId, string $rawBody, Request $request): array
    {
        $payloadHash = hash('sha256', $rawBody);

        return DB::transaction(function () use ($input, $eventId, $payloadHash, $request): array {
            $existing = CarrierWebhookReceipt::query()
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof CarrierWebhookReceipt) {
                if (hash_equals($existing->payload_hash, $payloadHash) === false) {
                    throw new ApiDomainException(
                        'carrier.webhook_replay_conflict',
                        'شناسه رویداد قبلاً با محتوای دیگری دریافت شده است.',
                        409,
                    );
                }

                return [
                    'receipt' => $existing,
                    'shipment_leg' => $existing->shipmentLeg()->firstOrFail(),
                    'replayed' => true,
                ];
            }

            $carrier = trim($input['carrier']);
            $trackingCode = trim($input['tracking_code']);
            $candidates = ShipmentLeg::query()
                ->where('carrier', $carrier)
                ->where('tracking_code', $trackingCode)
                ->orderByDesc('sequence')
                ->limit(2)
                ->lockForUpdate()
                ->get();
            if ($candidates->isEmpty()) {
                throw new ApiDomainException('carrier.tracking_not_found', 'ارسال متناظر با این کد رهگیری پیدا نشد.', 404);
            }
            if ($candidates->count() !== 1) {
                throw new ApiDomainException(
                    'carrier.tracking_ambiguous',
                    'کد رهگیری به بیش از یک مرحله ارسال متصل است و نیاز به بررسی دارد.',
                    409,
                );
            }

            /** @var ShipmentLeg $leg */
            $leg = $candidates->first();
            $eventType = CarrierEventType::from($input['event_type']);
            $occurredAt = CarbonImmutable::parse($input['occurred_at'])->utc();
            if ($occurredAt->isAfter(now()->addMinutes(10))) {
                throw new ApiDomainException(
                    'carrier.event_time_invalid',
                    'زمان رویداد حامل بیش از محدوده مجاز در آینده است.',
                    422,
                );
            }

            if ($eventType === CarrierEventType::Delivered) {
                $evidenceReference = null;
                if (isset($input['evidence_reference'])) {
                    $candidateReference = trim((string) $input['evidence_reference']);
                    $evidenceReference = $candidateReference === '' ? null : $candidateReference;
                }

                $this->deliveries->confirm(
                    null,
                    $leg,
                    DeliveryConfirmationSource::Carrier,
                    [
                        'idempotency_key' => 'carrier-event:'.$eventId,
                        'proof_type' => 'carrier_scan',
                        'proof_payload' => [
                            'reference' => $evidenceReference,
                            'occurred_at' => $occurredAt->toIso8601String(),
                        ],
                    ],
                    $request,
                );
                $leg->refresh();
            } else {
                $target = ShipmentLegStatus::from($eventType->value);
                $this->applyTransition(
                    $leg,
                    $target,
                    $carrier,
                    $trackingCode,
                    'signed_carrier_webhook',
                    $occurredAt,
                    'carrier',
                    null,
                    $request,
                    true,
                );
            }

            $receipt = CarrierWebhookReceipt::query()->create([
                'event_id' => $eventId,
                'shipment_leg_id' => $leg->id,
                'carrier' => $carrier,
                'tracking_code' => $trackingCode,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
                'payload_hash' => $payloadHash,
                'signature_version' => 'v1',
                'received_at' => now(),
            ]);

            $this->audit->record(
                'carrier.webhook_event_applied',
                auditable: $receipt,
                metadata: [
                    'event_id' => $eventId,
                    'shipment_leg_id' => $leg->id,
                    'carrier' => $carrier,
                    'event_type' => $eventType->value,
                    'payload_hash' => $payloadHash,
                ],
                request: $request,
            );

            return [
                'receipt' => $receipt,
                'shipment_leg' => $leg->refresh(),
                'replayed' => false,
            ];
        }, 3);
    }

    private function applyTransition(
        ShipmentLeg $leg,
        ShipmentLegStatus $target,
        string $carrier,
        string $trackingCode,
        string $reason,
        CarbonImmutable $occurredAt,
        string $actorType,
        ?User $actor,
        Request $request,
        bool $webhook,
    ): void {
        $from = $leg->status;
        if ($from === $target && $leg->carrier === $carrier && $leg->tracking_code === $trackingCode) {
            return;
        }

        $allowed = $webhook ? $this->webhookTransitions($from) : $this->manualTransitions($from);
        if (in_array($target, $allowed, true) === false) {
            throw new ApiDomainException(
                'carrier.invalid_transition',
                "تغییر وضعیت ارسال از {$from->value} به {$target->value} مجاز نیست.",
                409,
            );
        }

        $leg->forceFill([
            'carrier' => $carrier,
            'tracking_code' => $trackingCode,
            'status' => $target,
            'picked_up_at' => $target === ShipmentLegStatus::PickedUp
                ? ($leg->picked_up_at ?? $occurredAt)
                : $leg->picked_up_at,
        ])->save();

        if ($leg->sub_order_id !== null) {
            $legacyShipment = Shipment::query()
                ->where('sub_order_id', $leg->sub_order_id)
                ->lockForUpdate()
                ->first();
            if ($legacyShipment instanceof Shipment) {
                $isMoving = in_array($target, [ShipmentLegStatus::PickedUp, ShipmentLegStatus::InTransit], true);
                $legacyShipment->forceFill([
                    'carrier' => $carrier,
                    'tracking_code' => $trackingCode,
                    'status' => $isMoving ? 'shipped' : $target->value,
                    'shipped_at' => $isMoving
                        ? ($legacyShipment->shipped_at ?? $leg->picked_up_at ?? $occurredAt)
                        : $legacyShipment->shipped_at,
                ])->save();
            }
        }

        OrderEvent::query()->create([
            'order_id' => $leg->order_id,
            'sub_order_id' => $leg->sub_order_id,
            'shipment_leg_id' => $leg->id,
            'event_type' => 'shipment.carrier_status_changed',
            'previous_state' => $from->value,
            'next_state' => $target->value,
            'actor_type' => $actorType,
            'actor_user_id' => $actor?->id,
            'request_id' => $request->attributes->get('request_id'),
            'reason_code' => $webhook ? 'signed_carrier_event' : 'administrator_carrier_operation',
            'customer_title' => $this->customerTitle($target),
            'customer_description' => null,
            'internal_metadata' => [
                'carrier' => $carrier,
                'reason' => mb_substr($reason, 0, 500),
                'source' => $webhook ? 'signed_webhook' : 'manual_admin',
            ],
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);
    }

    /** @return list<ShipmentLegStatus> */
    private function webhookTransitions(ShipmentLegStatus $from): array
    {
        return match ($from) {
            ShipmentLegStatus::Planned => [ShipmentLegStatus::AwaitingPickup, ShipmentLegStatus::PickedUp, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::AwaitingPickup => [ShipmentLegStatus::PickedUp, ShipmentLegStatus::PickupFailed, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::PickedUp => [ShipmentLegStatus::InTransit, ShipmentLegStatus::DeliveryFailed, ShipmentLegStatus::Lost, ShipmentLegStatus::Damaged, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::InTransit => [ShipmentLegStatus::DeliveryFailed, ShipmentLegStatus::Lost, ShipmentLegStatus::Damaged, ShipmentLegStatus::RequiresReview],
            default => [],
        };
    }

    /** @return list<ShipmentLegStatus> */
    private function manualTransitions(ShipmentLegStatus $from): array
    {
        return match ($from) {
            ShipmentLegStatus::Planned => [ShipmentLegStatus::AwaitingPickup, ShipmentLegStatus::PickedUp, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::AwaitingPickup => [ShipmentLegStatus::PickedUp, ShipmentLegStatus::PickupFailed, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::PickedUp => [ShipmentLegStatus::InTransit, ShipmentLegStatus::DeliveryFailed, ShipmentLegStatus::Lost, ShipmentLegStatus::Damaged, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::InTransit => [ShipmentLegStatus::DeliveryFailed, ShipmentLegStatus::Lost, ShipmentLegStatus::Damaged, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::PickupFailed => [ShipmentLegStatus::AwaitingPickup, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::DeliveryFailed => [ShipmentLegStatus::InTransit, ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::Lost, ShipmentLegStatus::Damaged => [ShipmentLegStatus::RequiresReview],
            ShipmentLegStatus::RequiresReview => [ShipmentLegStatus::AwaitingPickup, ShipmentLegStatus::InTransit],
            ShipmentLegStatus::Delivered => [],
        };
    }

    private function customerTitle(ShipmentLegStatus $status): string
    {
        return match ($status) {
            ShipmentLegStatus::AwaitingPickup => 'ارسال آماده تحویل به حامل است',
            ShipmentLegStatus::PickedUp => 'بسته تحویل حامل شد',
            ShipmentLegStatus::InTransit => 'بسته در مسیر است',
            ShipmentLegStatus::PickupFailed, ShipmentLegStatus::DeliveryFailed, ShipmentLegStatus::Lost, ShipmentLegStatus::Damaged, ShipmentLegStatus::RequiresReview => 'وضعیت ارسال در حال بررسی است',
            default => 'وضعیت ارسال به‌روزرسانی شد',
        };
    }
}
