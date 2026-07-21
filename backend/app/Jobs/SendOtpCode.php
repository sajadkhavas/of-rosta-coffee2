<?php

namespace App\Jobs;

use App\Contracts\OtpSender;
use App\Models\OtpChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class SendOtpCode implements ShouldQueue
{
    use FoundationQueueable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public function __construct(
        public readonly string $challengeId,
        public readonly string $mobile,
        public readonly string $purpose,
        public readonly string $encryptedCode,
    ) {
        $this->onQueue('notifications');
    }

    public static function forPlaintextCode(
        string $challengeId,
        string $mobile,
        string $purpose,
        string $code,
    ): self {
        return new self(
            $challengeId,
            $mobile,
            $purpose,
            Crypt::encryptString($code),
        );
    }

    public function handle(OtpSender $sender): void
    {
        $challenge = OtpChallenge::query()->find($this->challengeId);
        if (! $challenge || $challenge->isConsumed() || $challenge->isLocked() || $challenge->isExpired()) {
            return;
        }

        try {
            $code = Crypt::decryptString($this->encryptedCode);
        } catch (DecryptException $exception) {
            $challenge->forceFill(['locked_at' => now()])->save();
            throw $exception;
        }

        $sender->send($this->mobile, $code, $this->purpose, $this->challengeId);
    }

    public function failed(?Throwable $exception): void
    {
        OtpChallenge::query()
            ->whereKey($this->challengeId)
            ->whereNull('consumed_at')
            ->update(['locked_at' => now(), 'updated_at' => now()]);
    }
}
