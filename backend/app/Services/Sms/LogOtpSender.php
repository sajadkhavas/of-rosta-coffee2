<?php

namespace App\Services\Sms;

use App\Contracts\OtpSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use LogicException;

final class LogOtpSender implements OtpSender
{
    public const CACHE_PREFIX = 'rosta:local:otp:';

    public function isAvailable(): bool
    {
        return app()->environment('local')
            && (bool) config('rosta.otp.enabled', false)
            && (string) config('rosta.otp.driver') === 'log';
    }

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): string {
        if (! $this->isAvailable()) {
            throw new LogicException('The local OTP sender is forbidden outside the local environment.');
        }

        Cache::put(
            self::CACHE_PREFIX.$challengeId,
            Crypt::encryptString($code),
            now()->addMinutes(3),
        );

        Log::notice('Local OTP prepared for one-time CLI consumption.', [
            'challenge_id' => $challengeId,
            'mobile_suffix' => substr($mobile, -4),
            'purpose' => $purpose,
        ]);

        return 'local-'.$challengeId;
    }
}
