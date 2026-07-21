<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'mobile' => '09123456789',
            'name' => 'سجاد',
            'email' => 'sajad@example.test',
        ]);
    }
}
