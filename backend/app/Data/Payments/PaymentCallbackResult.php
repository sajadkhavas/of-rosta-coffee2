<?php

namespace App\Data\Payments;

final readonly class PaymentCallbackResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $providerAuthority,
        public string $providerEventId,
        public string $payloadHash,
        public array $metadata = [],
    ) {}
}
