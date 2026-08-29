<?php

namespace App\Contracts;

interface CarrierProvider
{
    public function key(): string;

    public function supportsAutomatedDispatch(): bool;

    /** @return array{carrier:string,tracking_code:string} */
    public function normalizeAssignment(string $carrier, string $trackingCode): array;
}
