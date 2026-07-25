<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->index('order_id', 'sub_orders_order_id_fk_index');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->dropIndex('sub_orders_order_id_fk_index');
        });
    }
};
