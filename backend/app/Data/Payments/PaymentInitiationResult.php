<?php

namespace App\Data\Payments;

final readonly class PaymentInitiationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $redirectUrl,
        public string $providerAuthority,
        public array $metadata = [],
    ) {}
}
