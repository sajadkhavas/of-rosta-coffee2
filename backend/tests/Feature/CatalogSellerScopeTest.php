<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Roastery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class CatalogSellerScopeTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_seller_catalog_is_strictly_scoped_to_its_roastery(): void
    {
        $owner = User::factory()->create();
        $owned = $this->roastery('owned');
        $foreign = $this->roastery('foreign');

        $this->authenticateWithRole(
            $owner,
            Role::RoasteryOwner,
            'roastery',
            $owned->id,
        );

        $origin = Origin::query()->create([
            'name' => 'برزیل',
            'slug' => 'brazil',
            'country_code' => 'BRA',
        ]);

        $foreignProduct = $this->product($foreign, $origin, 'foreign-product');

        $this->patchJson(
            '/api/v1/seller/roasteries/'.$foreign->id.'/products/'.$foreignProduct->id,
            ['name' => 'دستکاری'],
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'request.forbidden');

        $this->postJson(
            '/api/v1/seller/roasteries/'.$owned->id.'/products',
            [
                'origin_id' => $origin->id,
                'name' => 'محصول مجاز',
                'slug' => 'allowed-product',
                'processing_method' => 'natural',
                'roast_level' => 'light',
                'arabica_percentage' => 100,
                'tasting_notes' => ['میوه‌ای'],
                'unexpected_state' => 'rejected',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'request.validation_failed');

        $created = $this->postJson(
            '/api/v1/seller/roasteries/'.$owned->id.'/products',
            [
                'origin_id' => $origin->id,
                'name' => 'محصول مجاز',
                'slug' => 'allowed-product',
                'processing_method' => 'natural',
                'roast_level' => 'light',
                'arabica_percentage' => 100,
                'tasting_notes' => ['میوه‌ای'],
            ],
        )
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        $this->postJson(
            '/api/v1/seller/roasteries/'.$owned->id.'/products/'.$created['id'].'/variants',
            [
                'sku' => 'BAD-WEIGHT',
                'weight_grams' => 200,
                'price' => 1_000_000,
            ],
        )->assertUnprocessable();

        $this->postJson(
            '/api/v1/seller/roasteries/'.$owned->id.'/products/'.$created['id'].'/variants',
            [
                'sku' => 'GOOD-WEIGHT',
                'weight_grams' => 250,
                'price' => 1_000_000,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.weight_grams', 250);

        $this->postJson(
            '/api/v1/seller/roasteries/'.$owned->id.'/products/'.$created['id'].'/variants',
            [
                'sku' => 'DUPLICATE-WEIGHT',
                'weight_grams' => 250,
                'price' => 1_100_000,
            ],
        )->assertUnprocessable();
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

    private function product(Roastery $roastery, Origin $origin, string $slug): Product
    {
        return Product::query()->create([
            'roastery_id' => $roastery->id,
            'origin_id' => $origin->id,
            'name' => $slug,
            'slug' => $slug,
            'description' => '',
            'processing_method' => 'washed',
            'roast_level' => 'medium',
            'arabica_percentage' => 100,
            'tasting_notes' => [],
            'brewing_suggestions' => [],
            'status' => 'draft',
        ]);
    }
}
