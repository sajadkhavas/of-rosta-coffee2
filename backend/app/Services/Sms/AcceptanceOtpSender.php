<?php

namespace App\Services\Sms;

use App\Contracts\OtpSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use LogicException;

final class AcceptanceOtpSender implements OtpSender
{
    public const CACHE_PREFIX = 'rosta:acceptance:otp:';

    public function isAvailable(): bool
    {
        return app()->environment('testing')
            && (string) config('services.sms.driver') === 'acceptance';
    }

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): void {
        if (! $this->isAvailable()) {
            throw new LogicException('The acceptance OTP sender is restricted to the testing environment.');
        }

        Cache::put(
            self::CACHE_PREFIX.$challengeId,
            Crypt::encryptString($code),
            now()->addMinutes(3),
        );
    }
}
