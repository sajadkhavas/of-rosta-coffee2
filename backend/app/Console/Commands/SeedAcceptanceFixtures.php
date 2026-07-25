<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RostaAcceptanceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SeedAcceptanceFixtures extends Command
{
    protected $signature = 'rosta:acceptance-fixtures {--json : Print a redacted JSON fixture manifest}';

    protected $description = 'Seed deterministic non-production fixtures for integrated Rosta acceptance';

    public function handle(RostaAcceptanceSeeder $seeder): int
    {
        if (! app()->environment(['local', 'testing'])) {
            return $this->fail('Acceptance fixtures are allowed only in local and testing environments.');
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('products')) {
            return $this->fail('Run the complete database migrations before acceptance fixtures.');
        }

        if (User::query()->exists()) {
            return $this->fail('Acceptance fixtures require an empty migrated database.');
        }

        try {
            $seeder->setContainer(app())->setCommand($this);
            $seeder();
        } catch (Throwable $exception) {
            return $this->fail('Acceptance fixture seeding failed: '.$exception->getMessage());
        }

        $customer = User::query()->where('mobile', '09123456789')->firstOrFail();
        $administrator = User::query()->where('mobile', '09120000001')->firstOrFail();
        $seller = User::query()->where('mobile', '09120000002')->firstOrFail();
        $roastery = Roastery::query()->where('slug', 'rosta-roastery')->firstOrFail();
        $product = Product::query()->where('slug', 'ethiopia-sidamo-whole-bean')->firstOrFail();
        $address = Address::query()
            ->where('user_id', $customer->id)
            ->where('is_default', true)
            ->firstOrFail();

        $roles = UserRole::query()
            ->whereIn('user_id', [$customer->id, $administrator->id, $seller->id])
            ->orderBy('role')
            ->get(['user_id', 'role', 'scope_type', 'scope_id']);

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->orderBy('weight_grams')
            ->get(['id', 'sku', 'weight_grams', 'price', 'currency', 'stock_on_hand']);

        $report = [
            'ready' => true,
            'environment' => app()->environment(),
            'contract_version' => config('rosta.contract_version'),
            'generated_at' => gmdate(DATE_ATOM),
            'fixtures' => [
                'customer' => [
                    'id' => $customer->id,
                    'alias' => 'acceptance-customer',
                    'default_address_id' => $address->id,
                ],
                'administrator' => [
                    'id' => $administrator->id,
                    'alias' => 'acceptance-administrator',
                ],
                'seller' => [
                    'id' => $seller->id,
                    'alias' => 'acceptance-seller',
                    'roastery_id' => $roastery->id,
                ],
                'roastery' => [
                    'id' => $roastery->id,
                    'slug' => $roastery->slug,
                    'status' => $roastery->status,
                ],
                'product' => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'status' => $product->status,
                    'variants' => $variants->toArray(),
                ],
                'roles' => $roles->toArray(),
            ],
            'marker' => 'ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE',
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->info('ROSTA_R3A_ACCEPTANCE_FIXTURES_COMPLETE');
            $this->line('Customer, administrator and scoped seller fixtures are ready.');
            $this->line('Published whole-bean variants: '.$variants->pluck('weight_grams')->implode(', ').'g');
        }

        return self::SUCCESS;
    }

    private function fail(string $message): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ready' => false,
                'environment' => app()->environment(),
                'error' => $message,
                'marker' => null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
