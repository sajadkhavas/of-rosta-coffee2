<?php

namespace App\Services\Payments\Data;

final readonly class PaymentVerificationResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public bool $verified,
        public string $state,
        public ?string $referenceId,
        public ?string $gatewayCode,
        public ?string $failureCode,
        public ?string $message,
        public array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function verified(
        string $referenceId,
        ?string $gatewayCode = null,
        array $payload = [],
    ): self {
        return new self(
            true,
            'verified',
            $referenceId,
            $gatewayCode,
            null,
            null,
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function failed(
        string $state,
        string $failureCode,
        string $message,
        ?string $gatewayCode = null,
        array $payload = [],
    ): self {
        return new self(
            false,
            $state,
            null,
            $gatewayCode,
            $failureCode,
            $message,
            $payload,
        );
    }
}
