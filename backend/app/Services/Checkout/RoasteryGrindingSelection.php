<?php

namespace App\Services\Checkout;

use App\Enums\GrindingAvailability;
use App\Enums\OrderItemServiceStatus;
use App\Exceptions\ApiDomainException;
use App\Models\CheckoutQuoteItemService;
use App\Models\GrindingProfile;
use App\Models\OrderItemService;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;

final class RoasteryGrindingSelection
{
    private const int MAX_MONEY = 9_007_199_254_740_991;

    /**
     * @return array{
     *     profile: GrindingProfile,
     *     unit_fee: int,
     *     line_total: int,
     *     pricing_snapshot: array<string, mixed>,
     *     service_snapshot: array<string, mixed>
     * }|null
     */
    public function resolve(
        Roastery $roastery,
        ProductVariant $variant,
        ?string $profileId,
        int $quantity,
    ): ?array {
        if ($profileId === null || trim($profileId) === '') {
            return null;
        }

        $capability = $this->capability($roastery->id, lock: false);
        $profile = $this->assertSelection(
            $capability,
            $roastery,
            $variant,
            $profileId,
            $quantity,
        );
        $unitFee = $capability->fee_amount;
        $lineTotal = $this->multiplyMoney($unitFee, $quantity);

        return [
            'profile' => $profile,
            'unit_fee' => $unitFee,
            'line_total' => $lineTotal,
            'pricing_snapshot' => [
                'version' => 'r5f-roastery-grinding-v1',
                'fee_mode' => $capability->fee_mode->value,
                'unit_fee' => $unitFee,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'currency' => 'IRR',
            ],
            'service_snapshot' => [
                'version' => 'r5f-roastery-grinding-v1',
                'provider_type' => 'roastery',
                'provider_roastery_id' => $roastery->id,
                'weight_grams' => $variant->weight_grams,
                'quantity' => $quantity,
                'preparation_minutes' => $capability->preparation_minutes,
                'capacity_per_day' => $capability->capacity_per_day,
                'profile' => [
                    'id' => $profile->id,
                    'code' => $profile->code,
                    'version' => $profile->version,
                    'name' => $profile->public_name,
                    'brew_method' => $profile->brew_method,
                    'recipe_snapshot' => $profile->recipe_snapshot,
                ],
                'label' => 'آسیاب روستری: '.$profile->public_name,
            ],
        ];
    }

    public function assertQuoteServiceOrderable(
        CheckoutQuoteItemService $service,
        ProductVariant $variant,
        int $quantity,
    ): void {
        if (
            $service->service_type !== 'grinding'
            || $service->provider_type !== 'roastery'
            || $service->provider_roastery_id === null
            || $service->grinding_profile_id === null
            || $variant->product->roastery_id !== $service->provider_roastery_id
        ) {
            throw new ApiDomainException(
                'checkout.grinding_snapshot_invalid',
                'اطلاعات سرویس آسیاب Quote معتبر نیست.',
                409,
            );
        }

        if (
            $service->service_fee !== $service->total_amount
            || $service->packaging_fee !== 0
            || $service->shipping_fee !== 0
            || $service->tax_amount !== 0
        ) {
            throw new ApiDomainException(
                'checkout.grinding_snapshot_invalid',
                'مبالغ سرویس آسیاب Quote سازگار نیست.',
                409,
            );
        }

        $snapshot = $service->service_snapshot;
        if (
            ($snapshot['weight_grams'] ?? null) !== $variant->weight_grams
            || ($snapshot['quantity'] ?? null) !== $quantity
            || ($snapshot['profile']['id'] ?? null) !== $service->grinding_profile_id
        ) {
            throw new ApiDomainException(
                'checkout.grinding_snapshot_invalid',
                'Snapshot سرویس آسیاب با آیتم سفارش سازگار نیست.',
                409,
            );
        }

        $roastery = $variant->product->roastery;
        $capability = $this->capability($service->provider_roastery_id, lock: true);
        $this->assertSelection(
            $capability,
            $roastery,
            $variant,
            $service->grinding_profile_id,
            $quantity,
        );
    }

    private function capability(string $roasteryId, bool $lock): RoasteryGrindingCapability
    {
        $query = RoasteryGrindingCapability::query()
            ->with('profiles')
            ->where('roastery_id', $roasteryId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $capability = $query->first();
        if (! $capability instanceof RoasteryGrindingCapability) {
            throw new ApiDomainException(
                'checkout.grinding_unavailable',
                'این روستری سرویس آسیاب فعال ندارد.',
                409,
            );
        }

        return $capability;
    }

    private function assertSelection(
        RoasteryGrindingCapability $capability,
        Roastery $roastery,
        ProductVariant $variant,
        string $profileId,
        int $quantity,
    ): GrindingProfile {
        if (
            ! $capability->is_active
            || $capability->availability !== GrindingAvailability::Available
        ) {
            throw new ApiDomainException(
                'checkout.grinding_unavailable',
                'سرویس آسیاب این روستری در حال حاضر فعال نیست.',
                409,
            );
        }

        if ($variant->product->roastery_id !== $roastery->id) {
            throw new ApiDomainException(
                'checkout.grinding_provider_mismatch',
                'سرویس آسیاب باید توسط روستری همان محصول ارائه شود.',
                409,
            );
        }

        $supportedWeights = array_map('intval', $capability->supported_weights ?? []);
        if (! in_array($variant->weight_grams, $supportedWeights, true)) {
            throw new ApiDomainException(
                'checkout.grinding_weight_unsupported',
                'این وزن توسط سرویس آسیاب روستری پشتیبانی نمی‌شود.',
                409,
            );
        }

        $profile = $capability->profiles
            ->first(static fn (GrindingProfile $candidate): bool => (
                $candidate->id === $profileId && $candidate->is_active
            ));

        if (! $profile instanceof GrindingProfile) {
            throw new ApiDomainException(
                'checkout.grinding_profile_unsupported',
                'پروفایل آسیاب انتخاب‌شده توسط این روستری ارائه نمی‌شود.',
                409,
            );
        }

        $this->assertCapacity($capability, $quantity);

        return $profile;
    }

    private function assertCapacity(
        RoasteryGrindingCapability $capability,
        int $requestedQuantity,
    ): void {
        if ($capability->capacity_per_day === null) {
            return;
        }

        $used = (int) OrderItemService::query()
            ->join('order_items', 'order_items.id', '=', 'order_item_services.order_item_id')
            ->where('order_item_services.service_type', 'grinding')
            ->where('order_item_services.provider_type', 'roastery')
            ->where('order_item_services.provider_roastery_id', $capability->roastery_id)
            ->whereNotIn('order_item_services.status', [
                OrderItemServiceStatus::Failed->value,
                OrderItemServiceStatus::Cancelled->value,
            ])
            ->whereDate('order_item_services.created_at', now()->toDateString())
            ->sum('order_items.quantity');

        if ($used + $requestedQuantity > $capability->capacity_per_day) {
            throw new ApiDomainException(
                'checkout.grinding_capacity_unavailable',
                'ظرفیت امروز سرویس آسیاب این روستری کافی نیست.',
                409,
            );
        }
    }

    private function multiplyMoney(int $amount, int $multiplier): int
    {
        if (
            $amount < 0
            || $multiplier < 0
            || ($multiplier > 0 && $amount > intdiv(self::MAX_MONEY, $multiplier))
        ) {
            throw new ApiDomainException(
                'checkout.amount_out_of_range',
                'مبلغ محاسبه‌شده خارج از محدوده مجاز است.',
                409,
            );
        }

        return $amount * $multiplier;
    }
}
