<?php

namespace App\Services\Sms;

use App\Contracts\OtpSender;
use App\Exceptions\KavenegarDeliveryException;
use App\Services\Kavenegar\KavenegarClient;
use App\Support\IranMobile;

final class KavenegarOtpSender implements OtpSender
{
    public function __construct(
        private readonly KavenegarClient $client,
    ) {}

    public function isAvailable(): bool
    {
        $templates = $this->templates();

        return (bool) config('rosta.otp.enabled', false)
            && (string) config('rosta.otp.driver') === 'kavenegar'
            && $this->client->isAvailable()
            && collect(['login', 'register', 'verify_mobile'])->every(
                static fn (string $purpose): bool => isset($templates[$purpose])
                    && is_string($templates[$purpose])
                    && preg_match('/^[A-Za-z0-9_-]{2,100}$/', $templates[$purpose]) === 1,
            );
    }

    public function send(
        string $mobile,
        string $code,
        string $purpose,
        string $challengeId,
    ): string {
        $normalizedMobile = IranMobile::normalize($mobile);
        $template = $this->templates()[$purpose] ?? null;

        if (! $this->isAvailable()) {
            throw new KavenegarDeliveryException(
                $this->client->circuitRetryAfterSeconds() > 0
                    ? 'circuit_open'
                    : 'otp_configuration_invalid',
                retryable: $this->client->circuitRetryAfterSeconds() > 0,
                retryAfterSeconds: $this->client->circuitRetryAfterSeconds() ?: null,
            );
        }

        if (
            ! IranMobile::isValid($normalizedMobile)
            || preg_match('/^\d{6}$/', $code) !== 1
            || ! is_string($template)
        ) {
            throw new KavenegarDeliveryException('otp_payload_invalid');
        }

        return $this->client->verifyLookup($normalizedMobile, $code, $template);
    }

    /** @return array<string, mixed> */
    private function templates(): array
    {
        return (array) config('rosta.otp.kavenegar.templates', []);
    }
}
