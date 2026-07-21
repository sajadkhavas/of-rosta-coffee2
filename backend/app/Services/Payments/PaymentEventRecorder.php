<?php

namespace App\Services\Payments;

use App\Enums\PaymentEventType;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;

final class PaymentEventRecorder
{
    /**
     * @param array<string, mixed> $payload
     */
    public function record(
        PaymentAttempt $attempt,
        PaymentEventType $type,
        array $payload = [],
        ?string $providerEventId = null,
    ): PaymentEvent {
        $normalized = $this->canonicalize($payload);
        $payloadHash = hash('sha256', json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        if ($providerEventId !== null) {
            $existing = PaymentEvent::query()
                ->where('payment_attempt_id', $attempt->id)
                ->where('provider_event_id', $providerEventId)
                ->first();

            if ($existing instanceof PaymentEvent) {
                return $existing;
            }
        }

        return PaymentEvent::query()->create([
            'payment_attempt_id' => $attempt->id,
            'type' => $type,
            'provider_event_id' => $providerEventId,
            'payload_hash' => $payloadHash,
            'payload' => $normalized,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $value[$key] = array_is_list($item)
                ? array_map(
                    fn (mixed $child): mixed => is_array($child)
                        ? $this->canonicalize($child)
                        : $child,
                    $item,
                )
                : $this->canonicalize($item);
        }

        return $value;
    }
}
