<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->timestamp('delivery_confirmed_at')->nullable()->after('delivered_at')->index();
            $table->timestamp('dispute_window_ends_at')->nullable()->after('delivery_confirmed_at')->index();
            $table->string('settlement_hold_code', 64)->nullable()->after('dispute_window_ends_at')->index();
            $table->text('settlement_hold_note')->nullable()->after('settlement_hold_code');
            $table->timestamp('settlement_released_at')->nullable()->after('settlement_hold_note')->index();
            $table->index(
                ['status', 'settlement_hold_code', 'dispute_window_ends_at'],
                'sub_orders_settlement_release_lookup',
            );
        });

        Schema::create('shipment_delivery_confirmations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('shipment_leg_id')->constrained('shipment_legs')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->index();
            $table->string('idempotency_key', 160)->unique();
            $table->string('proof_type', 64);
            $table->longText('proof_payload')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('confirmed_at')->index();
            $table->timestamps();

            $table->unique(['shipment_leg_id'], 'shipment_leg_single_delivery_confirmation');
            $table->index(['sub_order_id', 'confirmed_at']);
        });

        Schema::create('settlement_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('processed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->char('currency', 3)->default('IRR');
            $table->unsignedBigInteger('gross_total');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('net_total');
            $table->unsignedInteger('allocation_count');
            $table->string('idempotency_key', 160)->unique();
            $table->string('payout_reference', 190)->nullable()->unique();
            $table->string('failure_code', 160)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('processing_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['roastery_id', 'status', 'created_at']);
        });

        Schema::create('settlement_batch_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('settlement_batch_id')->constrained('settlement_batches')->cascadeOnDelete();
            $table->foreignUlid('settlement_allocation_id')->constrained('settlement_allocations')->restrictOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->timestamp('attached_at');

            $table->unique('settlement_allocation_id', 'settlement_allocation_single_batch');
            $table->index(['settlement_batch_id', 'attached_at']);
        });
    }

    public function down(): void
    {
        if (
            DB::table('shipment_delivery_confirmations')->exists()
            || DB::table('settlement_batches')->exists()
            || DB::table('sub_orders')->whereNotNull('delivery_confirmed_at')->exists()
        ) {
            throw new RuntimeException('R5I rollback refused because delivery or settlement records exist.');
        }

        Schema::dropIfExists('settlement_batch_allocations');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('shipment_delivery_confirmations');

        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->dropIndex('sub_orders_settlement_release_lookup');
            $table->dropColumn([
                'delivery_confirmed_at',
                'dispute_window_ends_at',
                'settlement_hold_code',
                'settlement_hold_note',
                'settlement_released_at',
            ]);
        });
    }
};
