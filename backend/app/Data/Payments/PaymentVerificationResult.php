<?php

namespace App\Data\Payments;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

final readonly class PaymentVerificationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public PaymentStatus $status,
        public int $amount,
        public string $currency,
        public ?string $providerTransactionId,
        public ?string $providerEventId,
        public ?CarbonImmutable $verifiedAt,
        public array $metadata = [],
    ) {}
}
