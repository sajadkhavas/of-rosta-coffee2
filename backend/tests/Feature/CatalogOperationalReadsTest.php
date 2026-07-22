<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\MediaAsset;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class CatalogOperationalReadsTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_seller_can_read_only_its_catalog_operational_data(): void
    {
        $owner = User::factory()->create();
        $roastery = $this->roastery('owned');
        $foreign = $this->roastery('foreign');

        $this->authenticateWithRole(
            $owner,
            Role::RoasteryOwner,
            'roastery',
            $roastery->id,
        );

        $origin = Origin::query()->create([
            'name' => 'رواندا',
            'slug' => 'rwanda',
            'country_code' => 'RWA',
        ]);

        $media = MediaAsset::query()->create([
            'roastery_id' => $roastery->id,
            'alt' => 'تصویر محصول',
            'width' => 1200,
            'height' => 1200,
            'sources' => [[
                'url' => 'https://cdn.rosta.test/product.webp',
                'width' => 1200,
                'format' => 'webp',
            ]],
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'primary_media_id' => $media->id,
            'name' => 'رواندا',
            'slug' => 'rwanda-product',
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => ['مرکبات'],
            'brewing_suggestions' => [],
            'status' => 'draft',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RWA-250',
            'weight_grams' => 250,
            'price' => 2_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 6,
            'stock_reserved' => 0,
        ]);

        $batch = RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'RWA-001',
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        $ledger = StockLedgerEntry::query()->create([
            'variant_id' => $variant->id,
            'roast_batch_id' => $batch->id,
            'actor_id' => $owner->id,
            'delta' => 6,
            'balance_after' => 6,
            'reason' => 'opening',
            'idempotency_key' => 'test:operational:opening:0001',
            'metadata' => [],
        ]);

        $this->getJson('/api/v1/seller/origins')
            ->assertOk()
            ->assertJsonPath('data.0.id', $origin->id);

        $this->getJson('/api/v1/seller/roasteries/'.$roastery->id.'/media')
            ->assertOk()
            ->assertJsonPath('data.0.id', $media->id);

        $this->getJson('/api/v1/seller/roasteries/'.$roastery->id.'/products/'.$product->id.'/roast-batches')
            ->assertOk()
            ->assertJsonPath('data.0.id', $batch->id);

        $this->getJson('/api/v1/seller/roasteries/'.$roastery->id.'/variants/'.$variant->id.'/stock-ledger')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ledger->id);

        $this->getJson('/api/v1/seller/roasteries/'.$foreign->id.'/media')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'request.not_found');
    }

    public function test_administrator_can_create_and_update_origins(): void
    {
        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);

        $origin = $this->postJson('/api/v1/admin/origins', [
            'name' => 'گواتمالا',
            'slug' => 'guatemala',
            'country_code' => 'gtm',
        ])
            ->assertCreated()
            ->assertJsonPath('data.country_code', 'GTM')
            ->json('data');

        $this->patchJson('/api/v1/admin/origins/'.$origin['id'], [
            'name' => 'گواتمالا آنتیگوا',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'گواتمالا آنتیگوا');

        $this->getJson('/api/v1/admin/origins')
            ->assertOk()
            ->assertJsonPath('data.0.id', $origin['id']);
    }

    private function roastery(string $slug): Roastery
    {
        return Roastery::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'description' => '',
            'status' => 'pending',
        ]);
    }
}
