<?php

namespace App\Console\Commands;

use App\Services\Sms\AcceptanceOtpSender;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class ConsumeAcceptanceOtp extends Command
{
    protected $signature = 'rosta:acceptance-otp {challenge_id : OTP challenge ULID}';

    protected $description = 'Consume a one-time encrypted OTP generated only for browser acceptance';

    public function handle(): int
    {
        if (! app()->environment('testing') || config('services.sms.driver') !== 'acceptance') {
            $this->components->error('Acceptance OTP consumption is restricted to the testing driver.');

            return self::FAILURE;
        }

        $challengeId = trim((string) $this->argument('challenge_id'));
        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $challengeId)) {
            $this->components->error('A valid challenge ULID is required.');

            return self::INVALID;
        }

        $encrypted = Cache::pull(AcceptanceOtpSender::CACHE_PREFIX.$challengeId);
        if (! is_string($encrypted) || $encrypted === '') {
            $this->components->error('The acceptance OTP is missing, expired or already consumed.');

            return self::FAILURE;
        }

        try {
            $code = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            $this->components->error('The acceptance OTP could not be decrypted.');

            return self::FAILURE;
        }

        if (! preg_match('/^\d{6}$/', $code)) {
            $this->components->error('The acceptance OTP payload is invalid.');

            return self::FAILURE;
        }

        $this->line($code);

        return self::SUCCESS;
    }
}
