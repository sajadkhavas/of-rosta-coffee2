<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamp('preparing_at')->nullable()->index();
            $table->timestamp('ready_to_ship_at')->nullable()->index();
            $table->timestamp('shipped_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->string('rejection_reason', 1000)->nullable();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sub_order_id')->unique()->constrained('sub_orders')->cascadeOnDelete();
            $table->string('carrier', 120);
            $table->string('tracking_code', 200);
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('shipped_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['carrier', 'tracking_code']);
        });

        Schema::create('sub_order_status_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48);
            $table->string('reason', 1000)->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sub_order_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });

        Schema::create('order_internal_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->nullable()->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('inventory_restocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUlid('roast_batch_id')->nullable()->constrained('roast_batches')->nullOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('reason', 80);
            $table->foreignUlid('ledger_entry_id')->nullable()->constrained('stock_ledger_entries')->nullOnDelete();
            $table->timestamp('restocked_at')->index();
            $table->timestamps();
            $table->unique(['order_item_id', 'reason']);
            $table->index(['variant_id', 'restocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_restocks');
        Schema::dropIfExists('order_internal_notes');
        Schema::dropIfExists('sub_order_status_history');
        Schema::dropIfExists('shipments');

        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'accepted_at',
                'rejected_at',
                'preparing_at',
                'ready_to_ship_at',
                'shipped_at',
                'delivered_at',
                'cancelled_at',
                'rejection_reason',
            ]);
        });
    }
};
