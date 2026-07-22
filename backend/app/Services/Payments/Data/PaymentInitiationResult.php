<?php

namespace App\Services\Payments\Data;

final readonly class PaymentInitiationResult
{
    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     */
    public function __construct(
        public string $authority,
        public string $redirectUrl,
        public ?string $gatewayCode = null,
        public array $requestPayload = [],
        public array $responsePayload = [],
    ) {}
}
