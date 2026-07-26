<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, array{version: int, public_name: string, brew_method: string}> */
    private const array PROFILES = [
        'turkish' => ['version' => 1, 'public_name' => 'قهوه ترک', 'brew_method' => 'turkish'],
        'home-espresso-pressurised' => ['version' => 1, 'public_name' => 'اسپرسوساز خانگی با بسکت تحت فشار', 'brew_method' => 'espresso'],
        'moka-pot' => ['version' => 1, 'public_name' => 'موکاپات', 'brew_method' => 'moka_pot'],
        'aeropress' => ['version' => 1, 'public_name' => 'ائروپرس', 'brew_method' => 'aeropress'],
        'v60' => ['version' => 2, 'public_name' => 'V60', 'brew_method' => 'v60'],
        'chemex' => ['version' => 1, 'public_name' => 'کمکس', 'brew_method' => 'chemex'],
        'filter-machine' => ['version' => 1, 'public_name' => 'قهوه‌ساز فیلتری', 'brew_method' => 'filter_machine'],
        'french-press' => ['version' => 1, 'public_name' => 'فرنچ پرس', 'brew_method' => 'french_press'],
        'cold-brew' => ['version' => 1, 'public_name' => 'کلد برو', 'brew_method' => 'cold_brew'],
    ];

    public function up(): void
    {
        foreach (self::PROFILES as $code => $profile) {
            $existing = DB::table('grinding_profiles')
                ->where('code', $code)
                ->where('version', $profile['version'])
                ->first();

            $values = [
                'public_name' => $profile['public_name'],
                'brew_method' => $profile['brew_method'],
                'recipe_snapshot' => json_encode([
                    'contract_version' => 'r5e-v1',
                    'recipe_owner' => 'rosta_operations',
                    'customer_editable' => false,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'updated_at' => now(),
            ];

            if ($existing !== null) {
                DB::table('grinding_profiles')
                    ->where('id', $existing->id)
                    ->update($values);

                continue;
            }

            DB::table('grinding_profiles')->insert([
                'id' => (string) Str::ulid(),
                'code' => $code,
                'version' => $profile['version'],
                ...$values,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::PROFILES as $code => $profile) {
            DB::table('grinding_profiles')
                ->where('code', $code)
                ->where('version', $profile['version'])
                ->delete();
        }
    }
};
