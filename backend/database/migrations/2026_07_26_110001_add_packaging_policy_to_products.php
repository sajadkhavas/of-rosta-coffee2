<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('packaging_fee_mode', 16)
                ->default('free')
                ->after('brewing_suggestions');
            $table->unsignedBigInteger('packaging_fee_amount')
                ->default(0)
                ->after('packaging_fee_mode');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['packaging_fee_mode', 'packaging_fee_amount']);
        });
    }
};
