<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, array{public_name: string, brew_method: string}> */
    private const array PROFILES = [
        'turkish' => ['public_name' => 'قهوه ترک', 'brew_method' => 'turkish'],
        'home-espresso-pressurised' => ['public_name' => 'اسپرسوساز خانگی با بسکت تحت فشار', 'brew_method' => 'espresso'],
        'moka-pot' => ['public_name' => 'موکاپات', 'brew_method' => 'moka_pot'],
        'aeropress' => ['public_name' => 'ائروپرس', 'brew_method' => 'aeropress'],
        'v60' => ['public_name' => 'V60', 'brew_method' => 'v60'],
        'chemex' => ['public_name' => 'کمکس', 'brew_method' => 'chemex'],
        'filter-machine' => ['public_name' => 'قهوه‌ساز فیلتری', 'brew_method' => 'filter_machine'],
        'french-press' => ['public_name' => 'فرنچ پرس', 'brew_method' => 'french_press'],
        'cold-brew' => ['public_name' => 'کلد برو', 'brew_method' => 'cold_brew'],
    ];

    public function up(): void
    {
        foreach (self::PROFILES as $code => $profile) {
            $existing = DB::table('grinding_profiles')
                ->where('code', $code)
                ->where('version', 1)
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
                'version' => 1,
                ...$values,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('grinding_profiles')
            ->where('version', 1)
            ->whereIn('code', array_keys(self::PROFILES))
            ->delete();
    }
};
