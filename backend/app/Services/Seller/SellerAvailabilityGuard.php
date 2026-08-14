<?php

namespace App\Services\Seller;

use App\Exceptions\ApiDomainException;
use App\Models\ProductVariant;
use App\Models\Roastery;

final class SellerAvailabilityGuard
{
    public function __construct(private readonly RoasteryAvailability $availability) {}

    /** @param list<array{variant_id: string, quantity: int, grinding_profile_id?: string|null}> $items */
    public function assertAcceptingOrders(array $items): void
    {
        $variantIds = collect($items)
            ->pluck('variant_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values();

        $roasteries = ProductVariant::query()
            ->with('product.roastery')
            ->whereIn('id', $variantIds)
            ->get()
            ->map(static fn (ProductVariant $variant): Roastery => $variant->product->roastery)
            ->unique('id');

        foreach ($roasteries as $roastery) {
            $snapshot = $this->availability->snapshot($roastery);
            if (($snapshot['accepting_orders'] ?? true) === true) {
                continue;
            }

            throw new ApiDomainException(
                'cart.roastery_temporarily_closed',
                'این روستری موقتاً سفارش جدید نمی‌پذیرد.',
                409,
                [
                    'roastery_id' => [$roastery->id],
                    'closed_until' => [(string) ($snapshot['closed_until'] ?? '')],
                ],
            );
        }
    }
}
