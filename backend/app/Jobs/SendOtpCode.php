<?php

namespace App\Jobs;

use App\Contracts\OtpSender;
use App\Enums\OtpDeliveryStatus;
use App\Exceptions\KavenegarDeliveryException;
use App\Models\OtpChallenge;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SendOtpCode implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

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
        $challenge = DB::transaction(function (): ?OtpChallenge {
            $locked = OtpChallenge::query()
                ->whereKey($this->challengeId)
                ->lockForUpdate()
                ->first();

            if (
                ! $locked
                || $locked->isConsumed()
                || $locked->isLocked()
                || $locked->isExpired()
            ) {
                return null;
            }

            if ($locked->delivery_status === OtpDeliveryStatus::Processing) {
                $locked->forceFill([
                    'delivery_status' => OtpDeliveryStatus::Unknown,
                    'delivery_error_code' => 'worker_interrupted_outcome_unknown',
                    'delivery_failed_at' => now(),
                ])->save();

                return null;
            }

            if ($locked->delivery_status !== OtpDeliveryStatus::Pending) {
                return null;
            }

            $locked->forceFill([
                'delivery_status' => OtpDeliveryStatus::Processing,
                'delivery_attempts' => $locked->delivery_attempts + 1,
                'delivery_provider' => (string) config('rosta.otp.driver', 'disabled'),
                'delivery_error_code' => null,
                'delivery_started_at' => now(),
                'delivery_failed_at' => null,
            ])->save();

            return $locked;
        }, 3);

        if (! $challenge) {
            return;
        }

        try {
            $code = Crypt::decryptString($this->encryptedCode);
        } catch (DecryptException) {
            $this->recordTerminal(OtpDeliveryStatus::Failed, 'encrypted_code_invalid', lockChallenge: true);

            return;
        }

        try {
            $providerMessageId = $sender->send(
                $this->mobile,
                $code,
                $this->purpose,
                $this->challengeId,
            );
        } catch (KavenegarDeliveryException $exception) {
            if (
                $exception->retryable
                && ! $exception->ambiguous
                && $challenge->delivery_attempts < (int) config('rosta.otp.delivery_max_attempts', 3)
            ) {
                $this->recordRetry($exception);

                return;
            }

            $this->recordTerminal(
                $exception->ambiguous ? OtpDeliveryStatus::Unknown : OtpDeliveryStatus::Failed,
                $exception->reasonCode,
                lockChallenge: ! $exception->ambiguous,
            );

            return;
        } catch (Throwable) {
            $this->recordTerminal(
                OtpDeliveryStatus::Unknown,
                'sender_outcome_unknown',
            );

            return;
        }

        try {
            DB::transaction(function () use ($providerMessageId): void {
                $locked = OtpChallenge::query()
                    ->whereKey($this->challengeId)
                    ->lockForUpdate()
                    ->first();
                if (! $locked || $locked->delivery_status !== OtpDeliveryStatus::Processing) {
                    return;
                }

                $locked->forceFill([
                    'delivery_status' => OtpDeliveryStatus::Sent,
                    'provider_message_id' => $providerMessageId,
                    'delivery_error_code' => null,
                    'delivered_at' => now(),
                    'delivery_failed_at' => null,
                ])->save();
            }, 3);
        } catch (Throwable) {
            // A provider may already have accepted the message. Persisting an
            // unknown outcome prevents an automatic duplicate send.
            $this->recordTerminal(
                OtpDeliveryStatus::Unknown,
                'provider_accepted_persistence_unknown',
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->recordTerminal(
            OtpDeliveryStatus::Unknown,
            'queue_exhausted_outcome_unknown',
        );
    }

    private function recordRetry(KavenegarDeliveryException $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $locked = OtpChallenge::query()
                ->whereKey($this->challengeId)
                ->lockForUpdate()
                ->first();
            if (! $locked || $locked->delivery_status !== OtpDeliveryStatus::Processing) {
                return;
            }

            $locked->forceFill([
                'delivery_status' => OtpDeliveryStatus::Pending,
                'delivery_error_code' => $exception->reasonCode,
                'delivery_failed_at' => now(),
            ])->save();
        }, 3);

        $base = max(
            5,
            $exception->retryAfterSeconds
                ?? (int) config('rosta.kavenegar.retry_base_seconds', 30),
        );
        $this->release(min(900, $base + random_int(0, max(1, intdiv($base, 4)))));
    }

    private function recordTerminal(
        OtpDeliveryStatus $status,
        string $errorCode,
        bool $lockChallenge = false,
    ): void {
        try {
            DB::transaction(function () use ($status, $errorCode, $lockChallenge): void {
                $locked = OtpChallenge::query()
                    ->whereKey($this->challengeId)
                    ->lockForUpdate()
                    ->first();
                if (! $locked || $locked->delivery_status === OtpDeliveryStatus::Sent) {
                    return;
                }

                $locked->forceFill([
                    'delivery_status' => $status,
                    'delivery_error_code' => $errorCode,
                    'delivery_failed_at' => now(),
                    'locked_at' => $lockChallenge ? now() : $locked->locked_at,
                ])->save();
            }, 3);
        } catch (Throwable) {
            // Do not throw after an external delivery attempt: an automatic
            // retry could send a duplicate when the provider outcome is unknown.
        }
    }
}
