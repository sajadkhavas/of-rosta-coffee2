<?php

namespace App\Support;

final class IranMobile
{
    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    public static function normalize(string $value): string
    {
        $compact = preg_replace(
            '/[\s()\-]/u',
            '',
            trim(self::normalizeDigits($value)),
        ) ?? '';

        if (str_starts_with($compact, '+98')) {
            return '0'.substr($compact, 3);
        }

        if (str_starts_with($compact, '0098')) {
            return '0'.substr($compact, 4);
        }

        if (str_starts_with($compact, '98') && strlen($compact) === 12) {
            return '0'.substr($compact, 2);
        }

        return $compact;
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/^09\d{9}$/', self::normalize($value)) === 1;
    }
}
