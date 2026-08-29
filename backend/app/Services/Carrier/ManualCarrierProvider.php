<?php

namespace App\Services\Carrier;

use App\Contracts\CarrierProvider;
use App\Exceptions\ApiDomainException;

final class ManualCarrierProvider implements CarrierProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function supportsAutomatedDispatch(): bool
    {
        return false;
    }

    /** @return array{carrier:string,tracking_code:string} */
    public function normalizeAssignment(string $carrier, string $trackingCode): array
    {
        $carrier = trim($carrier);
        $trackingCode = trim($trackingCode);

        if ($carrier === '' || $trackingCode === '') {
            throw new ApiDomainException(
                'carrier.assignment_incomplete',
                'نام حامل و کد رهگیری برای ثبت دستی الزامی است.',
                422,
            );
        }

        return [
            'carrier' => mb_substr($carrier, 0, 120),
            'tracking_code' => mb_substr($trackingCode, 0, 200),
        ];
    }
}
