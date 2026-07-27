<?php

namespace App\Services\Checkout;

use App\Enums\GrindingAvailability;
use App\Enums\OrderItemServiceStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\CheckoutQuoteItemService;
use App\Models\GrindingProfile;
use App\Models\OrderItemService;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use App\Models\RostaHub;
use App\Models\RostaHubServiceZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RoasteryGrindingSelection
{
    private const int MAX_MONEY = 9_007_199_254_740_991;

    /**
     * @return array{
     *     profile: GrindingProfile,
     *     provider_type: 'roastery'|'rosta_hub',
     *     provider_roastery_id: string|null,
     *     provider_hub_id: string|null,
     *     unit_fee: int,
     *     line_total: int,
     *     route_shipping_total: int,
     *     pricing_snapshot: array<string, mixed>,
     *     service_snapshot: array<string, mixed>
     * }|null
     */
    public function resolve(
        Roastery $roastery,
        ProductVariant $variant,
        ?string $profileId,
        int $quantity,
        ?Address $address = null,
        bool $requireAddress = false,
    ): ?array {
        if ($profileId === null || trim($profileId) === '') {
            return null;
        }

        $capability = $this->capability($roastery->id, lock: false, required: false);
        if (
            $capability instanceof RoasteryGrindingCapability
            && $capability->is_active
            && $capability->availability === GrindingAvailability::Available
        ) {
            return $this->resolveRoastery(
                $capability,
                $roastery,
                $variant,
                $profileId,
                $quantity,
            );
        }

        if (
            ! $capability instanceof RoasteryGrindingCapability
            || ! $capability->is_active
            || $capability->availability !== GrindingAvailability::Unavailable
        ) {
            throw new ApiDomainException(
                'checkout.grinding_unavailable',
                'این روستری وضعیت معتبر برای ارجاع آسیاب به هاب ندارد.',
                409,
            );
        }

        return $this->resolveHub(
            $roastery,
            $variant,
            $profileId,
            $quantity,
            $address,
            $requireAddress,
        );
    }

    public function assertQuoteServiceOrderable(
        CheckoutQuoteItemService $service,
        ProductVariant $variant,
        int $quantity,
        ?array $addressSnapshot = null,
    ): void {
        if (
            $service->service_type !== 'grinding'
            || $service->grinding_profile_id === null
            || ! in_array($service->provider_type, ['roastery', 'rosta_hub'], true)
        ) {
            $this->invalidSnapshot('اطلاعات سرویس آسیاب Quote معتبر نیست.');
        }

        if (
            $service->service_fee !== $service->total_amount
            || $service->packaging_fee !== 0
            || $service->shipping_fee !== 0
            || $service->tax_amount !== 0
        ) {
            $this->invalidSnapshot('مبالغ سرویس آسیاب Quote سازگار نیست.');
        }

        $snapshot = $service->service_snapshot;
        if (
            ($snapshot['weight_grams'] ?? null) !== $variant->weight_grams
            || ($snapshot['quantity'] ?? null) !== $quantity
            || ($snapshot['profile']['id'] ?? null) !== $service->grinding_profile_id
            || ($snapshot['provider_type'] ?? null) !== $service->provider_type
        ) {
            $this->invalidSnapshot('Snapshot سرویس آسیاب با آیتم سفارش سازگار نیست.');
        }

        if ($service->provider_type === 'roastery') {
            $this->assertRoasteryQuoteService($service, $variant, $quantity);

            return;
        }

        $this->assertHubQuoteService($service, $variant, $quantity, $addressSnapshot);
    }

    /**
     * @return array{
     *     profile: GrindingProfile,
     *     provider_type: 'roastery',
     *     provider_roastery_id: string,
     *     provider_hub_id: null,
     *     unit_fee: int,
     *     line_total: int,
     *     route_shipping_total: 0,
     *     pricing_snapshot: array<string, mixed>,
     *     service_snapshot: array<string, mixed>
     * }
     */
    private function resolveRoastery(
        RoasteryGrindingCapability $capability,
        Roastery $roastery,
        ProductVariant $variant,
        string $profileId,
        int $quantity,
    ): array {
        $profile = $this->assertRoasterySelection(
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
            'provider_type' => 'roastery',
            'provider_roastery_id' => $roastery->id,
            'provider_hub_id' => null,
            'unit_fee' => $unitFee,
            'line_total' => $lineTotal,
            'route_shipping_total' => 0,
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
                'profile' => $this->profileSnapshot($profile),
                'label' => 'آسیاب روستری: '.$profile->public_name,
            ],
        ];
    }

    /**
     * @return array{
     *     profile: GrindingProfile,
     *     provider_type: 'rosta_hub',
     *     provider_roastery_id: null,
     *     provider_hub_id: string,
     *     unit_fee: int,
     *     line_total: int,
     *     route_shipping_total: int,
     *     pricing_snapshot: array<string, mixed>,
     *     service_snapshot: array<string, mixed>
     * }
     */
    private function resolveHub(
        Roastery $roastery,
        ProductVariant $variant,
        string $profileId,
        int $quantity,
        ?Address $address,
        bool $requireAddress,
    ): array {
        $profile = GrindingProfile::query()
            ->whereKey($profileId)
            ->where('is_active', true)
            ->first();

        if (! $profile instanceof GrindingProfile) {
            throw new ApiDomainException(
                'unsupported_grind_profile',
                'پروفایل آسیاب انتخاب‌شده فعال نیست.',
                409,
            );
        }

        $candidateQuery = RostaHub::query()
            ->with(['profiles', 'serviceZones'])
            ->where('is_active', true)
            ->whereJsonContains('supported_weights', $variant->weight_grams)
            ->whereHas('profiles', static fn (Builder $query): Builder => $query
                ->where('grinding_profiles.id', $profileId)
                ->where('grinding_profiles.is_active', true))
            ->whereHas('serviceZones', static fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereIn('city', ['تهران', 'کرج']))
            ->orderBy('code');

        /** @var Collection<int, RostaHub> $candidates */
        $candidates = $candidateQuery->get();
        if ($candidates->isEmpty()) {
            throw new ApiDomainException(
                'unsupported_grind_profile',
                'هیچ هاب فعالی این پروفایل و وزن را پشتیبانی نمی‌کند.',
                409,
            );
        }

        $zoneRequired = $requireAddress || $address instanceof Address;
        if ($zoneRequired && ! $address instanceof Address) {
            throw new ApiDomainException(
                'hub_zone_ineligible',
                'برای بررسی آسیاب هاب، آدرس تحویل معتبر لازم است.',
                409,
            );
        }

        $selectedHub = null;
        $selectedZone = null;
        foreach ($candidates as $hub) {
            if (! $this->isHubConfigurationValid($hub)) {
                continue;
            }

            $zone = $address instanceof Address
                ? $this->matchingZone($hub, $address)
                : null;

            if ($zoneRequired && ! $zone instanceof RostaHubServiceZone) {
                continue;
            }

            if (! $this->hasHubCapacity($hub, $quantity)) {
                continue;
            }

            $selectedHub = $hub;
            $selectedZone = $zone;
            break;
        }

        if (! $selectedHub instanceof RostaHub) {
            if ($zoneRequired) {
                if (! $address instanceof Address) {
                    throw new ApiDomainException(
                        'hub_zone_ineligible',
                        'آدرس معتبر برای مسیر هاب وجود ندارد.',
                        409,
                    );
                }
                $confirmedAddress = $address;
                $hasZone = $candidates->contains(
                    fn (RostaHub $hub): bool => $this->matchingZone($hub, $confirmedAddress) instanceof RostaHubServiceZone,
                );
                if (! $hasZone) {
                    throw new ApiDomainException(
                        'hub_zone_ineligible',
                        'آسیاب هاب رستا برای این آدرس فعال نیست.',
                        409,
                    );
                }
            }

            throw new ApiDomainException(
                'hub_capacity_unavailable',
                'ظرفیت امروز هاب رستا برای این درخواست کافی نیست.',
                409,
            );
        }

        $unitFee = $selectedHub->fee_amount;
        $lineTotal = $this->multiplyMoney($unitFee, $quantity);
        $inboundFee = $selectedZone?->inbound_shipping_fee ?? 0;
        $outboundFee = $selectedZone?->outbound_shipping_fee ?? 0;
        $routeShippingTotal = $this->addMoney($inboundFee, $outboundFee);

        return [
            'profile' => $profile,
            'provider_type' => 'rosta_hub',
            'provider_roastery_id' => null,
            'provider_hub_id' => $selectedHub->id,
            'unit_fee' => $unitFee,
            'line_total' => $lineTotal,
            'route_shipping_total' => $routeShippingTotal,
            'pricing_snapshot' => [
                'version' => 'r5g-rosta-hub-grinding-v1',
                'fee_mode' => $selectedHub->fee_mode->value,
                'unit_fee' => $unitFee,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'currency' => 'IRR',
            ],
            'service_snapshot' => [
                'version' => 'r5g-rosta-hub-grinding-v1',
                'provider_type' => 'rosta_hub',
                'provider_hub_id' => $selectedHub->id,
                'source_roastery_id' => $roastery->id,
                'weight_grams' => $variant->weight_grams,
                'quantity' => $quantity,
                'preparation_minutes' => $selectedHub->preparation_minutes,
                'capacity_per_day' => $selectedHub->capacity_per_day,
                'hub' => [
                    'id' => $selectedHub->id,
                    'code' => $selectedHub->code,
                    'name' => $selectedHub->name,
                    'province' => $selectedHub->province,
                    'city' => $selectedHub->city,
                ],
                'zone' => $selectedZone instanceof RostaHubServiceZone
                    ? [
                        'id' => $selectedZone->id,
                        'province' => $selectedZone->province,
                        'city' => $selectedZone->city,
                    ]
                    : null,
                'route' => [
                    'requires_address_confirmation' => ! $selectedZone instanceof RostaHubServiceZone,
                    'inbound_shipping_fee' => $inboundFee,
                    'outbound_shipping_fee' => $outboundFee,
                    'total_shipping_fee' => $routeShippingTotal,
                ],
                'profile' => $this->profileSnapshot($profile),
                'label' => 'آسیاب هاب رستا: '.$profile->public_name,
            ],
        ];
    }

    private function assertRoasteryQuoteService(
        CheckoutQuoteItemService $service,
        ProductVariant $variant,
        int $quantity,
    ): void {
        if (
            $service->provider_roastery_id === null
            || $service->provider_hub_id !== null
            || $variant->product->roastery_id !== $service->provider_roastery_id
        ) {
            $this->invalidSnapshot('ارائه‌دهنده روستری سرویس آسیاب معتبر نیست.');
        }

        $roastery = $variant->product->roastery;
        $capability = $this->capability($service->provider_roastery_id, lock: true, required: true);
        $this->assertRoasterySelection(
            $capability,
            $roastery,
            $variant,
            $service->grinding_profile_id,
            $quantity,
        );
        $this->assertCurrentFee($service, $capability->fee_amount, $quantity);
    }

    private function assertHubQuoteService(
        CheckoutQuoteItemService $service,
        ProductVariant $variant,
        int $quantity,
        ?array $addressSnapshot,
    ): void {
        if ($service->provider_hub_id === null || $service->provider_roastery_id !== null) {
            $this->invalidSnapshot('ارائه‌دهنده هاب سرویس آسیاب معتبر نیست.');
        }

        $capability = $this->capability(
            $variant->product->roastery_id,
            lock: true,
            required: false,
        );
        if (
            $capability instanceof RoasteryGrindingCapability
            && $capability->is_active
            && $capability->availability === GrindingAvailability::Available
        ) {
            throw new ApiDomainException(
                'checkout.grinding_provider_changed',
                'روستری اکنون سرویس آسیاب فعال دارد و مسیر هاب باید دوباره Quote شود.',
                409,
            );
        }

        $hub = RostaHub::query()
            ->with(['profiles', 'serviceZones'])
            ->whereKey($service->provider_hub_id)
            ->lockForUpdate()
            ->first();
        if (! $hub instanceof RostaHub || ! $hub->is_active || ! $this->isHubConfigurationValid($hub)) {
            throw new ApiDomainException(
                'hub_capacity_unavailable',
                'هاب انتخاب‌شده دیگر فعال یا معتبر نیست.',
                409,
            );
        }

        $supportedWeights = array_map('intval', $hub->supported_weights ?? []);
        if (! in_array($variant->weight_grams, $supportedWeights, true)) {
            throw new ApiDomainException(
                'unsupported_grind_profile',
                'وزن انتخاب‌شده دیگر توسط هاب پشتیبانی نمی‌شود.',
                409,
            );
        }

        $profileSupported = $hub->profiles->contains(
            static fn (GrindingProfile $profile): bool => $profile->id === $service->grinding_profile_id
                && $profile->is_active,
        );
        if (! $profileSupported) {
            throw new ApiDomainException(
                'unsupported_grind_profile',
                'پروفایل انتخاب‌شده دیگر توسط هاب پشتیبانی نمی‌شود.',
                409,
            );
        }

        if (! $this->hasHubCapacity($hub, $quantity)) {
            throw new ApiDomainException(
                'hub_capacity_unavailable',
                'ظرفیت امروز هاب رستا برای ثبت سفارش کافی نیست.',
                409,
            );
        }

        if (! is_array($addressSnapshot)) {
            $this->invalidSnapshot('Snapshot آدرس مسیر هاب وجود ندارد.');
        }

        $province = (string) ($addressSnapshot['province'] ?? '');
        $city = (string) ($addressSnapshot['city'] ?? '');
        $zone = $hub->serviceZones->first(
            static fn (RostaHubServiceZone $candidate): bool => $candidate->is_active
                && $candidate->province === $province
                && $candidate->city === $city,
        );
        if (! $zone instanceof RostaHubServiceZone) {
            throw new ApiDomainException(
                'hub_zone_ineligible',
                'آدرس سفارش دیگر در منطقه فعال هاب نیست.',
                409,
            );
        }

        $snapshot = $service->service_snapshot;
        if (
            ($snapshot['provider_hub_id'] ?? null) !== $hub->id
            || ($snapshot['zone']['id'] ?? null) !== $zone->id
            || ($snapshot['route']['inbound_shipping_fee'] ?? null) !== $zone->inbound_shipping_fee
            || ($snapshot['route']['outbound_shipping_fee'] ?? null) !== $zone->outbound_shipping_fee
        ) {
            $this->invalidSnapshot('Snapshot مسیر هاب با تنظیمات جاری سازگار نیست.');
        }

        $this->assertCurrentFee($service, $hub->fee_amount, $quantity);
    }

    private function capability(
        string $roasteryId,
        bool $lock,
        bool $required,
    ): ?RoasteryGrindingCapability {
        $query = RoasteryGrindingCapability::query()
            ->with('profiles')
            ->where('roastery_id', $roasteryId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $capability = $query->first();
        if ($required && ! $capability instanceof RoasteryGrindingCapability) {
            throw new ApiDomainException(
                'checkout.grinding_unavailable',
                'این روستری سرویس آسیاب فعال ندارد.',
                409,
            );
        }

        return $capability;
    }

    private function assertRoasterySelection(
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

        $this->assertRoasteryCapacity($capability, $quantity);

        return $profile;
    }

    private function assertRoasteryCapacity(
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

    private function hasHubCapacity(RostaHub $hub, int $requestedQuantity): bool
    {
        if ($hub->capacity_per_day === null) {
            return true;
        }

        $used = (int) OrderItemService::query()
            ->join('order_items', 'order_items.id', '=', 'order_item_services.order_item_id')
            ->where('order_item_services.service_type', 'grinding')
            ->where('order_item_services.provider_type', 'rosta_hub')
            ->where('order_item_services.provider_hub_id', $hub->id)
            ->whereNotIn('order_item_services.status', [
                OrderItemServiceStatus::Failed->value,
                OrderItemServiceStatus::Cancelled->value,
            ])
            ->whereDate('order_item_services.created_at', now()->toDateString())
            ->sum('order_items.quantity');

        return $used + $requestedQuantity <= $hub->capacity_per_day;
    }

    private function matchingZone(RostaHub $hub, Address $address): ?RostaHubServiceZone
    {
        if (! $this->isApprovedHubAddress($address->province, $address->city)) {
            return null;
        }

        return $hub->serviceZones
            ->filter(static fn (RostaHubServiceZone $zone): bool => $zone->is_active
                && $zone->province === $address->province
                && $zone->city === $address->city)
            ->sortByDesc('priority')
            ->first();
    }

    private function isApprovedHubAddress(string $province, string $city): bool
    {
        return ($province === 'تهران' && $city === 'تهران')
            || ($province === 'البرز' && $city === 'کرج');
    }

    private function isHubConfigurationValid(RostaHub $hub): bool
    {
        if ($hub->fee_amount < 0) {
            return false;
        }

        if ($hub->fee_mode->value === 'free' && $hub->fee_amount !== 0) {
            return false;
        }

        if ($hub->fee_mode->value === 'fixed' && $hub->fee_amount < 1) {
            return false;
        }

        $weights = array_map('intval', $hub->supported_weights ?? []);

        return $weights !== []
            && count(array_diff($weights, [50, 100, 250, 500, 1000])) === 0;
    }

    private function assertCurrentFee(
        CheckoutQuoteItemService $service,
        int $unitFee,
        int $quantity,
    ): void {
        $expected = $this->multiplyMoney($unitFee, $quantity);
        if (
            ($service->pricing_snapshot['unit_fee'] ?? null) !== $unitFee
            || ($service->pricing_snapshot['quantity'] ?? null) !== $quantity
            || $service->service_fee !== $expected
        ) {
            throw new ApiDomainException(
                'price_changed',
                'هزینه سرویس آسیاب تغییر کرده و Quote باید دوباره ساخته شود.',
                409,
            );
        }
    }

    /** @return array<string, mixed> */
    private function profileSnapshot(GrindingProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'code' => $profile->code,
            'version' => $profile->version,
            'name' => $profile->public_name,
            'brew_method' => $profile->brew_method,
            'recipe_snapshot' => $profile->recipe_snapshot,
        ];
    }

    private function invalidSnapshot(string $message): never
    {
        throw new ApiDomainException(
            'checkout.grinding_snapshot_invalid',
            $message,
            409,
        );
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

    private function addMoney(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > self::MAX_MONEY - $right) {
            throw new ApiDomainException(
                'checkout.amount_out_of_range',
                'مبلغ محاسبه‌شده خارج از محدوده مجاز است.',
                409,
            );
        }

        return $left + $right;
    }
}
