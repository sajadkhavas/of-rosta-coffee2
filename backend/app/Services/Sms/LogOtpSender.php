<?php

namespace App\Services\Sms;

use App\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;
use LogicException;

final class LogOtpSender implements OtpSender
{
    public function isAvailable(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): void {
        if (! $this->isAvailable()) {
            throw new LogicException('The OTP log sender is forbidden outside local and testing environments.');
        }

        Log::notice('Local OTP delivery', [
            'challenge_id' => $challengeId,
            'mobile_suffix' => substr($mobile, -4),
            'purpose' => $purpose,
            'code' => $code,
        ]);
    }
}
