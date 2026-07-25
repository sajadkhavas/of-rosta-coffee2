<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

final class RostaAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Rosta acceptance fixtures are restricted to local and testing environments.',
            );
        }

        if (User::query()->exists()) {
            throw new RuntimeException(
                'Rosta acceptance fixtures require an empty migrated database.',
            );
        }

        $this->call(DatabaseSeeder::class, true);

        $customer = User::query()
            ->where('mobile', '09123456789')
            ->firstOrFail();

        Address::query()->create([
            'user_id' => $customer->id,
            'title' => 'خانه پذیرش',
            'recipient_name' => 'مشتری پذیرش رستا',
            'recipient_mobile' => '09123456789',
            'province' => 'البرز',
            'city' => 'کرج',
            'address_line' => 'نشانی رزروشده برای پذیرش یکپارچه رستا',
            'postal_code' => '3199999999',
            'is_default' => true,
        ]);

        Roastery::query()->create([
            'name' => 'روستری خارج از محدوده پذیرش',
            'slug' => 'foreign-acceptance-roastery',
            'city' => 'تهران',
            'description' => 'Fixture مستقل برای اثبات مرز دسترسی Roastery-scoped.',
            'shipping_policy' => 'این Fixture نباید در پنل Seller اصلی قابل مدیریت باشد.',
            'status' => 'verified',
            'verified_at' => now(),
            'preparation_min_hours' => 24,
            'preparation_max_hours' => 48,
        ]);

        $product = Product::query()
            ->where('slug', 'ethiopia-sidamo-whole-bean')
            ->firstOrFail();

        $owner = User::query()
            ->where('mobile', '09120000002')
            ->firstOrFail();

        foreach ([
            ['sku' => 'ROSTA-ETH-100', 'weight' => 100, 'price' => 3_200_000, 'stock' => 40],
            ['sku' => 'ROSTA-ETH-500', 'weight' => 500, 'price' => 12_900_000, 'stock' => 20],
        ] as $fixture) {
            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => $fixture['sku'],
                'weight_grams' => $fixture['weight'],
                'price' => $fixture['price'],
                'currency' => 'IRR',
                'is_active' => true,
                'stock_on_hand' => $fixture['stock'],
                'stock_reserved' => 0,
            ]);

            $batch = RoastBatch::query()->create([
                'product_id' => $product->id,
                'batch_code' => 'ACCEPT-'.$fixture['weight'],
                'roasted_at' => now()->subDays(2),
                'available_from' => now()->subDay(),
                'is_active' => true,
            ]);

            StockLedgerEntry::query()->create([
                'variant_id' => $variant->id,
                'roast_batch_id' => $batch->id,
                'actor_id' => $owner->id,
                'delta' => $fixture['stock'],
                'balance_after' => $fixture['stock'],
                'reason' => 'opening',
                'idempotency_key' => 'acceptance:opening:'.$fixture['sku'],
                'metadata' => ['source' => 'r3a-acceptance-seeder'],
            ]);
        }
    }
}
