<?php

namespace App\Support;

use Illuminate\Http\Request;
use RuntimeException;

final class RequestFingerprint
{
    public static function ip(Request $request): ?string
    {
        return self::hashNullable($request->ip());
    }

    public static function userAgent(Request $request): ?string
    {
        return self::hashNullable($request->userAgent());
    }

    private static function hashNullable(?string $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for request fingerprinting.');
        }

        return hash_hmac('sha256', $normalized, $key);
    }
}
