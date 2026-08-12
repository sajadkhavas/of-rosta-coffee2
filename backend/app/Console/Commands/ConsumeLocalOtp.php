<?php

namespace App\Console\Commands;

use App\Services\Sms\LogOtpSender;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class ConsumeLocalOtp extends Command
{
    protected $signature = 'rosta:local-otp {challenge_id : OTP challenge ULID}';

    protected $description = 'Consume an encrypted local-development OTP exactly once';

    public function handle(): int
    {
        if (! app()->environment('local') || config('rosta.otp.driver') !== 'log') {
            $this->components->error('Local OTP consumption is restricted to the local log driver.');

            return self::FAILURE;
        }

        $challengeId = trim((string) $this->argument('challenge_id'));
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $challengeId) !== 1) {
            $this->components->error('The OTP challenge identifier is invalid.');

            return self::INVALID;
        }

        $encrypted = Cache::pull(LogOtpSender::CACHE_PREFIX.$challengeId);
        if (! is_string($encrypted)) {
            $this->components->error('The local OTP is missing, expired or already consumed.');

            return self::FAILURE;
        }

        try {
            $code = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            $this->components->error('The local OTP could not be decrypted.');

            return self::FAILURE;
        }

        if (preg_match('/^\d{6}$/', $code) !== 1) {
            $this->components->error('The local OTP payload is invalid.');

            return self::FAILURE;
        }

        $this->line($code);

        return self::SUCCESS;
    }
}
