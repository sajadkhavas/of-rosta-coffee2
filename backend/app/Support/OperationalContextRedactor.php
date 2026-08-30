<?php

namespace App\Support;

final class OperationalContextRedactor
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'otp',
        'code',
        'mobile',
        'phone',
        'email',
        'destination',
        'payload',
        'request_body',
        'response_body',
    ];

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function redact(array $context, int $depth = 0): array
    {
        if ($depth >= 5) {
            return ['_truncated' => true];
        }

        $safe = [];
        foreach (array_slice($context, 0, 50, true) as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($this->isSensitiveKey($normalizedKey)) {
                $safe[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redact($value, $depth + 1);
            } elseif (is_string($value)) {
                $safe[$key] = $this->redactString($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } else {
                $safe[$key] = '[unsupported]';
            }
        }

        return $safe;
    }

    public function redactString(string $value): string
    {
        $value = mb_substr($value, 0, 500);
        $value = (string) preg_replace(
            '/\bBearer\s+[A-Za-z0-9._~+\/=:-]+/i',
            'Bearer [redacted]',
            $value,
        );
        $value = (string) preg_replace(
            '/\b(api[_-]?key|token|secret|password|authorization|cookie)\s*[:=]\s*[^\s,;]+/i',
            '$1=[redacted]',
            $value,
        );
        $value = (string) preg_replace(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '[email]',
            $value,
        );

        return (string) preg_replace(
            '/(?<!\d)(?:(?:\+|00)?98|0)?9\d{9}(?!\d)/',
            '[mobile]',
            $value,
        );
    }

    public function maskDestination(string $destination): string
    {
        $destination = trim($destination);
        if ($destination === '') {
            return '';
        }

        if (str_contains($destination, '@')) {
            [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        $length = mb_strlen($destination);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return mb_substr($destination, 0, 3)
            .str_repeat('*', max(3, $length - 6))
            .mb_substr($destination, -3);
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
