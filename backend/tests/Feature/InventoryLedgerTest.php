<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class InventoryLedgerTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_stock_adjustment_is_idempotent_append_only_and_never_negative(): void
    {
        $owner = User::factory()->create();
        $roastery = Roastery::query()->create([
            'name' => 'روستری',
            'slug' => 'roastery',
            'description' => '',
            'status' => 'pending',
        ]);

        $this->authenticateWithRole(
            $owner,
            Role::RoasteryOwner,
            'roastery',
            $roastery->id,
        );

        $origin = Origin::query()->create([
            'name' => 'کلمبیا',
            'slug' => 'colombia',
            'country_code' => 'COL',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'کلمبیا',
            'slug' => 'colombia-product',
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'medium',
            'arabica_percentage' => 100,
            'tasting_notes' => [],
            'brewing_suggestions' => [],
            'status' => 'draft',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'COL-250',
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 0,
            'stock_reserved' => 0,
        ]);

        $payload = [
            'delta' => 12,
            'reason' => 'opening',
            'idempotency_key' => 'inventory:test:opening:0001',
        ];

        $first = $this->postJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/variants/'.$variant->id.'/stock-adjustments',
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.balance_after', 12)
            ->json('data');

        $second = $this->postJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/variants/'.$variant->id.'/stock-adjustments',
            $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.id', $first['id'])
            ->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('stock_ledger_entries', 1);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock_on_hand' => 12,
        ]);

        $this->postJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/variants/'.$variant->id.'/stock-adjustments',
            [
                ...$payload,
                'delta' => 13,
            ],
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'inventory.idempotency_conflict');

        $variant->refresh()->forceFill(['stock_reserved' => 10])->save();
        $this->postJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/variants/'.$variant->id.'/stock-adjustments',
            [
                'delta' => -3,
                'reason' => 'correction',
                'idempotency_key' => 'inventory:test:reserved:0001',
            ],
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'inventory.reserved_stock_conflict');

        $this->postJson(
            '/api/v1/seller/roasteries/'.$roastery->id.'/variants/'.$variant->id.'/stock-adjustments',
            [
                'delta' => -13,
                'reason' => 'correction',
                'idempotency_key' => 'inventory:test:negative:0001',
            ],
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'inventory.insufficient_stock');

        $entry = StockLedgerEntry::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['delta' => 99]);
    }
}
