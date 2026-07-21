<?php

namespace App\Services\Identity;

use RuntimeException;

final class OtpCodeHasher
{
    public function generate(): string
    {
        $length = (int) config('rosta.otp.length', 6);
        $maximum = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $maximum), $length, '0', STR_PAD_LEFT);
    }

    public function digest(
        string $challengeId,
        string $mobile,
        string $purpose,
        string $code,
    ): string {
        return hash_hmac(
            'sha256',
            implode('|', [$challengeId, $mobile, $purpose, $code]),
            $this->key(),
        );
    }

    public function verify(
        string $expectedDigest,
        string $challengeId,
        string $mobile,
        string $purpose,
        string $code,
    ): bool {
        return hash_equals(
            $expectedDigest,
            $this->digest($challengeId, $mobile, $purpose, $code),
        );
    }

    private function key(): string
    {
        $configured = (string) config('app.key');
        if ($configured === '') {
            throw new RuntimeException('APP_KEY is required for OTP hashing.');
        }

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);
            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('APP_KEY base64 value is invalid.');
            }

            return $decoded;
        }

        return $configured;
    }
}
