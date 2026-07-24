<?php

namespace App\Support;

use App\Exceptions\ApiDomainException;

final class SeoPath
{
    private const RESERVED_PREFIXES = [
        '/api',
        '/admin',
        '/panel',
        '/auth',
        '/checkout',
        '/cart',
        '/orders',
        '/profile',
        '/search',
    ];

    public static function normalize(string $path): string
    {
        $candidate = trim($path);
        if ($candidate === '' || ! str_starts_with($candidate, '/')) {
            throw new ApiDomainException(
                'seo.path_invalid',
                'مسیر سئو باید با / آغاز شود.',
                422,
            );
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $candidate) === 1) {
            throw new ApiDomainException(
                'seo.path_invalid',
                'Encoding مسیر سئو معتبر نیست.',
                422,
            );
        }

        for ($depth = 0; $depth < 5; $depth++) {
            $decoded = rawurldecode($candidate);
            if ($decoded === $candidate) {
                break;
            }
            $candidate = $decoded;
        }

        if (preg_match('/%[0-9A-Fa-f]{2}/', $candidate) === 1) {
            throw new ApiDomainException(
                'seo.path_invalid',
                'مسیر سئو بیش از حد رمزگذاری شده است.',
                422,
            );
        }

        if (
            str_contains($candidate, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1
        ) {
            throw new ApiDomainException(
                'seo.path_invalid',
                'مسیر سئو شامل نویسه نامعتبر است.',
                422,
            );
        }

        if (
            str_contains($candidate, '?')
            || str_contains($candidate, '#')
            || str_starts_with($candidate, '//')
        ) {
            throw new ApiDomainException(
                'seo.path_invalid',
                'مسیر Canonical نباید Query، Fragment یا Host داشته باشد.',
                422,
            );
        }

        $segments = array_values(array_filter(
            explode('/', $candidate),
            static fn (string $segment): bool => $segment !== '',
        ));

        if (array_filter(
            $segments,
            static fn (string $segment): bool => in_array(
                $segment,
                ['.', '..'],
                true,
            ),
        ) !== []) {
            throw new ApiDomainException(
                'seo.path_invalid',
                'مسیر سئو اجازه خروج از محدوده سایت را ندارد.',
                422,
            );
        }

        $normalized = '/'.implode('/', $segments);

        return $normalized === '' ? '/' : $normalized;
    }

    public static function assertPublic(string $path): string
    {
        $normalized = self::normalize($path);
        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (
                $normalized === $prefix
                || str_starts_with($normalized, $prefix.'/')
            ) {
                throw new ApiDomainException(
                    'seo.path_reserved',
                    'این مسیر برای بخش عمومی قابل استفاده نیست.',
                    422,
                );
            }
        }

        return $normalized;
    }
}
