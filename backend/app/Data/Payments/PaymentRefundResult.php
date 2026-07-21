<?php

namespace App\Data\Payments;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

final readonly class PaymentRefundResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public PaymentStatus $status,
        public string $providerRefundId,
        public int $amount,
        public string $currency,
        public CarbonImmutable $occurredAt,
        public array $metadata = [],
    ) {}
}
