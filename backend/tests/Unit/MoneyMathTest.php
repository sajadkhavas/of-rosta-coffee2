<?php

namespace Tests\Unit;

use App\Services\Finance\MoneyMath;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyMathTest extends TestCase
{
    #[DataProvider('basisPointCases')]
    public function test_basis_point_rounding_is_integer_and_explicit(int $amount, int $rate, string $mode, int $expected): void
    {
        $this->assertSame($expected, (new MoneyMath)->basisPoints($amount, $rate, $mode));
    }

    /** @return list<array{int, int, string, int}> */
    public static function basisPointCases(): array
    {
        return [
            [1, 5_000, 'floor', 0],
            [1, 5_000, 'half_up', 1],
            [10_001, 1, 'floor', 1],
            [10_001, 1, 'half_up', 1],
            [9_007_199_254_740_000, 10_000, 'half_up', 9_007_199_254_740_000],
        ];
    }

    public function test_largest_remainder_allocation_conserves_every_integer_amount(): void
    {
        $money = new MoneyMath;
        for ($amount = 0; $amount <= 1_000; $amount++) {
            $allocation = $money->allocate($amount, ['a' => 1, 'b' => 2, 'c' => 7]);
            $this->assertSame($amount, array_sum($allocation));
            $this->assertGreaterThanOrEqual(0, min($allocation));
            $this->assertSame($allocation, $money->allocate($amount, ['a' => 1, 'b' => 2, 'c' => 7]));
        }
    }

    public function test_positive_amount_cannot_be_allocated_without_weight(): void
    {
        $this->expectException(LogicException::class);
        (new MoneyMath)->allocate(1, ['zero' => 0]);
    }
}
