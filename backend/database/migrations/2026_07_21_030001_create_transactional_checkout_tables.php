<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->nullable()->constrained('roasteries')->cascadeOnDelete();
            $table->string('code', 100)->unique();
            $table->string('type', 32);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('minimum_subtotal')->default(0);
            $table->unsignedBigInteger('maximum_discount')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['roastery_id', 'is_active']);
        });

        Schema::create('shipping_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->string('province', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->unsignedBigInteger('base_cost');
            $table->unsignedBigInteger('free_over')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['roastery_id', 'province', 'city', 'is_active']);
        });

        Schema::create('checkout_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->foreignUlid('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('purpose', 32)->index();
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->char('currency', 3)->default('IRR');
            $table->json('address_snapshot')->nullable();
            $table->json('shipping_snapshot')->nullable();
            $table->json('warnings');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'expires_at']);
            $table->index(['roastery_id', 'expires_at']);
        });

        Schema::create('checkout_quote_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('quote_id')->constrained('checkout_quotes')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUlid('roast_batch_id')->nullable()->constrained('roast_batches')->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedBigInteger('line_total');
            $table->json('product_snapshot');
            $table->json('variant_snapshot');
            $table->json('roast_batch_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['quote_id', 'variant_id']);
            $table->index(['variant_id', 'quote_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->foreignUlid('quote_id')->unique()->constrained('checkout_quotes')->restrictOnDelete();
            $table->string('order_number', 120)->unique();
            $table->string('status', 48)->index();
            $table->json('address_snapshot');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_total');
            $table->unsignedBigInteger('discount_total');
            $table->unsignedBigInteger('grand_total');
            $table->char('currency', 3)->default('IRR');
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['roastery_id', 'status', 'created_at']);
        });

        Schema::create('sub_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->string('status', 48)->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_total');
            $table->timestamps();
            $table->index(['roastery_id', 'status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignUlid('roast_batch_id')->nullable()->constrained('roast_batches')->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->json('product_snapshot');
            $table->json('variant_snapshot');
            $table->json('roast_batch_snapshot')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['variant_id', 'created_at']);
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->foreignUlid('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignUlid('roast_batch_id')->nullable()->constrained('roast_batches')->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 32)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['variant_id', 'status', 'expires_at']);
        });

        Schema::create('order_idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 160);
            $table->char('request_hash', 64);
            $table->string('status', 32)->index();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('failure_code', 160)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_idempotency_keys');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('sub_orders');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checkout_quote_items');
        Schema::dropIfExists('checkout_quotes');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('coupons');
    }
};
