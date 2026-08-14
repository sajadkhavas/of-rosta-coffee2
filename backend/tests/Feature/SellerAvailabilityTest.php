<?php

namespace Tests\Feature;

use App\Enums\RoasteryMembershipRole;
use App\Enums\Role;
use App\Models\Address;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\RoasteryClosure;
use App\Models\RoasteryMembership;
use App\Models\RoasteryScheduleException;
use App\Models\RoasteryWeeklyHour;
use App\Models\ShippingRule;
use App\Models\User;
use App\Services\Seller\RoasteryAvailability;
use App\Services\Seller\SellerAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class SellerAvailabilityTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_timezone_dst_overnight_and_exception_rules_are_authoritative(): void
    {
        $roastery = $this->roastery('hours', 'Europe/Amsterdam');
        RoasteryWeeklyHour::query()->create([
            'roastery_id' => $roastery->id,
            'weekday' => 5,
            'opens_at' => '22:00',
            'closes_at' => '02:00',
            'is_closed' => false,
        ]);
        $availability = app(RoasteryAvailability::class);

        $overnight = $availability->snapshot(
            $roastery,
            CarbonImmutable::parse('2026-08-15 01:00:00', 'Europe/Amsterdam')->utc(),
        );
        $this->assertTrue($overnight['operating_now']);
        $this->assertSame('Europe/Amsterdam', $overnight['timezone']);

        RoasteryWeeklyHour::query()->where('roastery_id', $roastery->id)->delete();
        RoasteryWeeklyHour::query()->create([
            'roastery_id' => $roastery->id,
            'weekday' => 0,
            'opens_at' => '01:00',
            'closes_at' => '04:00',
            'is_closed' => false,
        ]);

        $springDst = $availability->snapshot(
            $roastery,
            CarbonImmutable::parse('2026-03-29 03:30:00', 'Europe/Amsterdam')->utc(),
        );
        $autumnDst = $availability->snapshot(
            $roastery,
            CarbonImmutable::parse('2026-10-25 02:30:00', 'Europe/Amsterdam')->utc(),
        );
        $this->assertTrue($springDst['operating_now']);
        $this->assertTrue($autumnDst['operating_now']);

        RoasteryScheduleException::query()->create([
            'roastery_id' => $roastery->id,
            'local_date' => '2026-03-29',
            'is_closed' => true,
            'public_reason' => 'تعطیلی برنامه‌ریزی‌شده',
        ]);
        $closedByException = $availability->snapshot(
            $roastery,
            CarbonImmutable::parse('2026-03-29 03:30:00', 'Europe/Amsterdam')->utc(),
        );
        $this->assertFalse($closedByException['operating_now']);
        $this->assertTrue($closedByException['accepting_orders']);
        $this->assertSame('outside_hours', $closedByException['status']);
        $this->assertSame('تعطیلی برنامه‌ریزی‌شده', $closedByException['public_reason']);
    }

    public function test_temporary_closure_overlap_is_rejected_and_end_time_auto_reopens(): void
    {
        $roastery = $this->roastery('closure');
        $owner = $this->member($roastery, RoasteryMembershipRole::Owner);
        $this->authenticateWithRole($owner, Role::Customer);
        $start = CarbonImmutable::parse('2026-08-20 10:00:00', 'UTC');
        $end = $start->addHours(4);

        $first = $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/closures", [
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'public_reason' => 'تعمیرات برنامه‌ریزی‌شده',
            'blocks_new_orders' => true,
        ])->assertCreated()->json('data.closure');

        $this->postJson("/api/v1/seller/roasteries/{$roastery->id}/closures", [
            'starts_at' => $start->addHour()->toIso8601String(),
            'ends_at' => $end->addHour()->toIso8601String(),
            'blocks_new_orders' => false,
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'seller.closure_overlap');

        $availability = app(RoasteryAvailability::class);
        $active = $availability->snapshot($roastery, $start->addHours(2));
        $reopened = $availability->snapshot($roastery, $end->addSecond());
        $this->assertSame('temporarily_closed', $active['status']);
        $this->assertFalse($active['accepting_orders']);
        $this->assertSame($first['ends_at'], $active['closed_until']);
        $this->assertTrue($reopened['accepting_orders']);
        $this->assertNotSame('temporarily_closed', $reopened['status']);
    }

    public function test_public_product_roastery_and_checkout_share_the_same_closure_truth(): void
    {
        [$customer, $address, $roastery, $product, $variant] = $this->commerceFixture();
        RoasteryClosure::query()->create([
            'roastery_id' => $roastery->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHours(3),
            'public_reason' => 'سرویس دوره‌ای تجهیزات',
            'blocks_new_orders' => true,
            'created_by' => $customer->id,
        ]);

        $this->getJson('/api/v1/roasteries/'.$roastery->slug)
            ->assertOk()
            ->assertJsonPath('availability.status', 'temporarily_closed')
            ->assertJsonPath('availability.accepting_orders', false)
            ->assertJsonPath('availability.public_reason', 'سرویس دوره‌ای تجهیزات');
        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('availability.status', 'temporarily_closed')
            ->assertJsonPath('availability.accepting_orders', false);

        $this->postJson('/api/v1/cart/validate', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'cart.roastery_temporarily_closed');

        $this->authenticateWithRole($customer, Role::Customer);
        $this->postJson('/api/v1/checkout/quote', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address_id' => $address->id,
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 'cart.roastery_temporarily_closed');

        RoasteryClosure::query()->where('roastery_id', $roastery->id)->update([
            'ends_at' => now()->subSecond(),
        ]);
        $this->postJson('/api/v1/checkout/quote', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'address_id' => $address->id,
        ])->assertOk();
    }

    public function test_non_blocking_closure_changes_public_status_without_blocking_cart(): void
    {
        [, , $roastery, $product, $variant] = $this->commerceFixture('non-block');
        $actor = User::factory()->create();
        RoasteryClosure::query()->create([
            'roastery_id' => $roastery->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'public_reason' => 'کاهش ظرفیت موقت',
            'blocks_new_orders' => false,
            'created_by' => $actor->id,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('availability.status', 'temporarily_closed')
            ->assertJsonPath('availability.accepting_orders', true);
        $this->postJson('/api/v1/cart/validate', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertOk();
    }

    private function roastery(string $suffix, string $timezone = 'Asia/Tehran'): Roastery
    {
        return Roastery::query()->create([
            'name' => 'روستری '.$suffix,
            'slug' => 'availability-'.$suffix,
            'description' => '',
            'status' => 'verified',
            'verified_at' => now(),
            'timezone' => $timezone,
        ]);
    }

    private function member(Roastery $roastery, RoasteryMembershipRole $role): User
    {
        $user = User::factory()->create();
        $membership = RoasteryMembership::query()->create([
            'roastery_id' => $roastery->id,
            'user_id' => $user->id,
            'role' => $role,
            'created_by' => $user->id,
        ]);
        app(SellerAccess::class)->syncLegacyRole($membership);

        return $user;
    }

    /** @return array{User, Address, Roastery, Product, ProductVariant} */
    private function commerceFixture(string $suffix = 'main'): array
    {
        $customer = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه',
            'recipient_name' => 'مشتری تست',
            'recipient_mobile' => '09123456789',
            'province' => 'البرز',
            'city' => 'کرج',
            'address_line' => 'نشانی تست',
            'postal_code' => '1234567890',
            'is_default' => true,
        ]);
        $roastery = $this->roastery('commerce-'.$suffix);
        ShippingRule::query()->create([
            'roastery_id' => $roastery->id,
            'province' => null,
            'city' => null,
            'base_cost' => 300_000,
            'free_over' => 10_000_000,
            'priority' => 0,
            'is_active' => true,
        ]);
        $origin = Origin::query()->create([
            'name' => 'اتیوپی '.$suffix,
            'slug' => 'availability-origin-'.$suffix,
            'country_code' => 'ETH',
        ]);
        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'محصول '.$suffix,
            'slug' => 'availability-product-'.$suffix,
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => ['مرکبات'],
            'brewing_suggestions' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'AVAIL-'.strtoupper($suffix),
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
        ]);
        RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'AVAIL-BATCH-'.strtoupper($suffix),
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        return [$customer, $address, $roastery, $product, $variant];
    }
}
