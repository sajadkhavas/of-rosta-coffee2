<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_commission_policies', function (Blueprint $table): void {
            $table->string('rounding_mode', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_commission_policies', function (Blueprint $table): void {
            $table->dropColumn('rounding_mode');
        });
    }
};
