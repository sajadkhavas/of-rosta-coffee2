<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class AcceptanceFixtureCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_fixtures_seed_an_empty_testing_database_and_emit_a_redacted_manifest(): void
    {
        $status = Artisan::call('rosta:acceptance-fixtures', ['--json' => true]);

        $this->assertSame(0, $status);

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($report['ready']);
        $this->assertSame('ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE', $report['marker']);
        $this->assertSame('acceptance-customer', $report['fixtures']['customer']['alias']);
        $this->assertSame('acceptance-administrator', $report['fixtures']['administrator']['alias']);
        $this->assertSame('acceptance-seller', $report['fixtures']['seller']['alias']);
        $this->assertSame('foreign-acceptance-roastery', $report['fixtures']['foreign_scope']['roastery_slug']);
        $this->assertSame('denied', $report['fixtures']['foreign_scope']['expected_seller_access']);
        $this->assertArrayNotHasKey('mobile', $report['fixtures']['customer']);
        $this->assertArrayNotHasKey('email', $report['fixtures']['customer']);
        $this->assertStringNotContainsString('otp', mb_strtolower(Artisan::output()));

        $this->assertDatabaseHas('users', ['mobile' => '09123456789']);
        $this->assertDatabaseHas('users', ['mobile' => '09120000001']);
        $this->assertDatabaseHas('users', ['mobile' => '09120000002']);
        $this->assertDatabaseHas('addresses', [
            'title' => 'خانه پذیرش',
            'postal_code' => '3199999999',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('roasteries', [
            'slug' => 'rosta-roastery',
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('roasteries', [
            'slug' => 'foreign-acceptance-roastery',
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('products', [
            'slug' => 'ethiopia-sidamo-whole-bean',
            'status' => 'published',
        ]);

        foreach ([100, 250, 500] as $weight) {
            $this->assertDatabaseHas('product_variants', [
                'weight_grams' => $weight,
                'is_active' => true,
            ]);
        }
    }

    public function test_acceptance_fixtures_refuse_duplicate_execution_without_adding_records(): void
    {
        $this->assertSame(0, Artisan::call('rosta:acceptance-fixtures', ['--json' => true]));

        $userCount = (int) User::query()->count();
        $variantCount = (int) ProductVariant::query()->count();

        $this->assertSame(1, Artisan::call('rosta:acceptance-fixtures', ['--json' => true]));

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($report['ready']);
        $this->assertNull($report['marker']);
        $this->assertStringContainsString('empty migrated database', $report['error']);
        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($variantCount, ProductVariant::query()->count());
    }

    public function test_acceptance_fixtures_are_forbidden_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $status = Artisan::call('rosta:acceptance-fixtures', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $status);
        $this->assertFalse($report['ready']);
        $this->assertNull($report['marker']);
        $this->assertStringContainsString('local and testing', $report['error']);
        $this->assertDatabaseCount('users', 0);
    }
}
