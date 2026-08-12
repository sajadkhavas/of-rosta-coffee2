<?php

namespace App\Services\Sms;

use App\Contracts\OtpSender;
use App\Exceptions\ApiDomainException;

final class DisabledOtpSender implements OtpSender
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): ?string {
        throw new ApiDomainException(
            'sms.unavailable',
            'سرویس ارسال کد تأیید فعال نیست.',
            503,
        );
    }
}
