<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_work_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUlid('order_item_service_id')->unique()->constrained('order_item_services')->cascadeOnDelete();
            $table->foreignUlid('hub_id')->constrained('rosta_hubs')->restrictOnDelete();
            $table->foreignUlid('inbound_shipment_leg_id')->unique()->constrained('shipment_legs')->restrictOnDelete();
            $table->foreignUlid('outbound_shipment_leg_id')->unique()->constrained('shipment_legs')->restrictOnDelete();
            $table->foreignUlid('assigned_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 48)->default('awaiting_inbound')->index();
            $table->unsignedInteger('revision')->default(0);
            $table->longText('snapshot');
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('grinding_started_at')->nullable()->index();
            $table->timestamp('quality_checked_at')->nullable()->index();
            $table->timestamp('packaged_at')->nullable()->index();
            $table->timestamp('ready_at')->nullable()->index();
            $table->timestamp('handed_off_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['hub_id', 'status', 'created_at'], 'hub_work_queue_lookup');
            $table->index(['assigned_operator_id', 'status'], 'hub_operator_queue_lookup');
        });

        Schema::create('hub_work_item_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_work_item_id')->constrained('hub_work_items')->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48)->index();
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48);
            $table->string('idempotency_key', 160)->unique();
            $table->string('public_label', 240);
            $table->longText('private_evidence')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at');

            $table->index(['hub_work_item_id', 'occurred_at'], 'hub_work_item_action_timeline');
        });
    }

    public function down(): void
    {
        if (DB::table('hub_work_items')->exists() || DB::table('hub_work_item_actions')->exists()) {
            throw new RuntimeException('R5J rollback refused because Hub operations records exist.');
        }

        Schema::dropIfExists('hub_work_item_actions');
        Schema::dropIfExists('hub_work_items');
    }
};
