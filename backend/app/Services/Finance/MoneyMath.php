<?php

namespace App\Services\Finance;

use LogicException;

final class MoneyMath
{
    public const int BASIS_POINTS = 10_000;

    public function basisPoints(int $amount, int $rate, string $roundingMode): int
    {
        if ($amount < 0 || $rate < 0 || $rate > self::BASIS_POINTS) {
            throw new LogicException('Money or basis-point rate is outside the supported boundary.');
        }

        $whole = intdiv($amount, self::BASIS_POINTS) * $rate;
        $remainderProduct = ($amount % self::BASIS_POINTS) * $rate;
        $result = $whole + intdiv($remainderProduct, self::BASIS_POINTS);

        return match ($roundingMode) {
            'floor' => $result,
            'half_up' => $result + (($remainderProduct % self::BASIS_POINTS) * 2 >= self::BASIS_POINTS ? 1 : 0),
            default => throw new LogicException('Unsupported financial rounding mode.'),
        };
    }

    /**
     * Deterministic largest-remainder allocation. Keys are the final stable tie breaker.
     *
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    public function allocate(int $amount, array $weights): array
    {
        if ($amount < 0 || array_filter($weights, static fn (int $weight): bool => $weight < 0) !== []) {
            throw new LogicException('Money allocation operands cannot be negative.');
        }
        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight === 0) {
            if ($amount !== 0) {
                throw new LogicException('A positive amount cannot be allocated over zero weight.');
            }

            return array_fill_keys(array_keys($weights), 0);
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $share = $this->multiplyDivideFloor($amount, $weight, $totalWeight);
            $allocations[$key] = $share;
            $remainders[$key] = $this->multiplyModulo($amount, $weight, $totalWeight);
            $allocated += $share;
        }

        uksort($remainders, static function (string $left, string $right) use ($remainders): int {
            return ($remainders[$right] <=> $remainders[$left]) ?: strcmp($left, $right);
        });
        $keys = array_keys($remainders);
        for ($remaining = $amount - $allocated, $index = 0; $remaining > 0; $remaining--, $index++) {
            $allocations[$keys[$index % count($keys)]]++;
        }

        return $allocations;
    }

    private function multiplyDivideFloor(int $left, int $right, int $divisor): int
    {
        $partWhole = intdiv($left, $divisor);
        $partRemainder = $left % $divisor;
        $totalWhole = 0;
        $totalRemainder = 0;
        $multiplier = $right;

        while ($multiplier > 0) {
            if (($multiplier & 1) === 1) {
                $totalWhole += $partWhole;
                $totalRemainder += $partRemainder;
                if ($totalRemainder >= $divisor) {
                    $totalWhole++;
                    $totalRemainder -= $divisor;
                }
            }
            $partWhole *= 2;
            $partRemainder *= 2;
            if ($partRemainder >= $divisor) {
                $partWhole++;
                $partRemainder -= $divisor;
            }
            $multiplier = intdiv($multiplier, 2);
        }

        return $totalWhole;
    }

    private function multiplyModulo(int $left, int $right, int $divisor): int
    {
        $result = 0;
        $addend = $left % $divisor;
        $multiplier = $right;
        while ($multiplier > 0) {
            if (($multiplier & 1) === 1) {
                $result = $result >= $divisor - $addend
                    ? $result - ($divisor - $addend)
                    : $result + $addend;
            }
            $addend = $addend >= $divisor - $addend
                ? $addend - ($divisor - $addend)
                : $addend + $addend;
            $multiplier = intdiv($multiplier, 2);
        }

        return $result;
    }
}
