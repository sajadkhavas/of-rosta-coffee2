<?php

namespace App\Services\Refunds\Data;

final readonly class RefundExecutionResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $state,
        public ?string $referenceId = null,
        public ?string $providerCode = null,
        public ?string $message = null,
        public array $payload = [],
    ) {}
}
