<?php

namespace Tests\Feature;

use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_exposes_published_products_of_verified_roasteries(): void
    {
        $origin = $this->origin();
        $verified = $this->roastery('verified-roastery', true);
        $pending = $this->roastery('pending-roastery', false);

        $published = $this->product($verified, $origin, 'published-product', 'published');
        $this->variant($published, 250, 10);

        $draft = $this->product($verified, $origin, 'draft-product', 'draft');
        $this->variant($draft, 250, 10);

        $hidden = $this->product($pending, $origin, 'hidden-product', 'published');
        $this->variant($hidden, 250, 10);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'published-product')
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.variants.0.weight_grams', 250)
            ->assertJsonPath('data.0.variants.0.is_available', true);

        $this->getJson('/api/v1/products/draft-product')->assertNotFound();
        $this->getJson('/api/v1/products/hidden-product')->assertNotFound();
    }

    public function test_product_detail_matches_frozen_whole_bean_contract(): void
    {
        $origin = $this->origin();
        $roastery = $this->roastery('rosta', true);
        $product = $this->product($roastery, $origin, 'ethiopia', 'published');
        $variant = $this->variant($product, 500, 8);

        RoastBatch::query()->create([
            'product_id' => $product->id,
            'batch_code' => 'BATCH-01',
            'roasted_at' => now()->subDay(),
            'available_from' => now()->subHours(12),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/products/ethiopia')
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.variants.0.id', $variant->id)
            ->assertJsonPath('data.variants.0.available_quantity', 8)
            ->assertJsonPath('data.latest_roast_batch.batch_code', 'BATCH-01')
            ->assertJsonStructure([
                'data' => [
                    'origin',
                    'roastery',
                    'variants',
                    'gallery',
                    'brewing_suggestions',
                    'seo',
                ],
            ]);

        $this->assertArrayNotHasKey('gr'.'ind', $response->json('data'));
    }

    private function origin(): Origin
    {
        return Origin::query()->create([
            'name' => 'اتیوپی',
            'slug' => 'ethiopia',
            'country_code' => 'ETH',
        ]);
    }

    private function roastery(string $slug, bool $verified): Roastery
    {
        return Roastery::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'description' => '',
            'status' => $verified ? 'verified' : 'pending',
            'verified_at' => $verified ? now() : null,
        ]);
    }

    private function product(
        Roastery $roastery,
        Origin $origin,
        string $slug,
        string $status,
    ): Product {
        return Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => $slug,
            'slug' => $slug,
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'medium',
            'arabica_percentage' => 100,
            'tasting_notes' => ['شکلات'],
            'brewing_suggestions' => [],
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function variant(Product $product, int $weight, int $stock): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.$product->slug,
            'weight_grams' => $weight,
            'price' => 1_000_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => $stock,
            'stock_reserved' => 0,
        ]);
    }
}
