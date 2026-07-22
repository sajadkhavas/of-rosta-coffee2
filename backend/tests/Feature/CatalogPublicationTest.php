<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class CatalogPublicationTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_only_administrator_can_verify_roastery_and_publish_product(): void
    {
        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);

        $roastery = Roastery::query()->create([
            'name' => 'در انتظار',
            'slug' => 'pending',
            'description' => '',
            'status' => 'pending',
        ]);

        $origin = Origin::query()->create([
            'name' => 'کنیا',
            'slug' => 'kenya',
            'country_code' => 'KEN',
        ]);

        $product = Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => 'کنیا',
            'slug' => 'kenya-product',
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'light',
            'arabica_percentage' => 100,
            'tasting_notes' => [],
            'brewing_suggestions' => [],
            'status' => 'review',
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'KEN-250',
            'weight_grams' => 250,
            'price' => 2_500_000,
            'currency' => 'IRR',
            'is_active' => true,
            'stock_on_hand' => 0,
            'stock_reserved' => 0,
        ]);

        $this->patchJson(
            '/api/v1/admin/products/'.$product->id.'/status',
            ['status' => 'published'],
        )
            ->assertConflict()
            ->assertJsonPath('error.code', 'catalog.roastery_not_verified');

        $this->patchJson(
            '/api/v1/admin/roasteries/'.$roastery->id.'/status',
            ['status' => 'verified'],
        )
            ->assertOk()
            ->assertJsonPath('data.is_verified', true);

        $this->patchJson(
            '/api/v1/admin/products/'.$product->id.'/status',
            ['status' => 'published'],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/products/kenya-product')->assertOk();
    }

    public function test_non_administrator_cannot_use_catalog_moderation_routes(): void
    {
        $customer = User::factory()->create();
        $this->authenticateWithRole($customer, Role::Customer);

        $this->getJson('/api/v1/admin/products')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'request.forbidden');

        $this->getJson('/api/v1/admin/roasteries')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'request.forbidden');
    }
}
