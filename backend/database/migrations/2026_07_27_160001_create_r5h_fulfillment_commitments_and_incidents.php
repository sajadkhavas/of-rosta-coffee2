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
            $table->timestamp('fulfillment_committed_at')->nullable()->after('accepted_at')->index();
            $table->timestamp('preparation_due_at')->nullable()->after('fulfillment_committed_at')->index();
            $table->timestamp('handoff_due_at')->nullable()->after('preparation_due_at')->index();
            $table->string('sla_status', 32)->default('not_started')->after('handoff_due_at')->index();
            $table->timestamp('incident_opened_at')->nullable()->after('sla_status');
            $table->timestamp('incident_resolved_at')->nullable()->after('incident_opened_at');
            $table->index(
                ['status', 'sla_status', 'preparation_due_at'],
                'sub_orders_preparation_sla_lookup',
            );
            $table->index(
                ['status', 'sla_status', 'handoff_due_at'],
                'sub_orders_handoff_sla_lookup',
            );
        });

        Schema::create('fulfillment_incidents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->foreignUlid('reported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('refund_attempt_id')->nullable()->constrained('refund_attempts')->nullOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->string('code', 64);
            $table->string('severity', 16)->default('high');
            $table->text('description');
            $table->string('resolution', 32)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['sub_order_id', 'status'], 'fulfillment_incident_open_lookup');
            $table->index(['roastery_id', 'reported_at'], 'fulfillment_incident_roastery_lookup');
        });
    }

    public function down(): void
    {
        $hasIncidents = DB::table('fulfillment_incidents')->exists();
        $hasCommitments = DB::table('sub_orders')
            ->whereNotNull('fulfillment_committed_at')
            ->exists();

        if ($hasIncidents || $hasCommitments) {
            throw new RuntimeException(
                'R5H rollback refused because fulfillment commitments or incidents exist.',
            );
        }

        Schema::dropIfExists('fulfillment_incidents');

        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'fulfillment_committed_at',
                'preparation_due_at',
                'handoff_due_at',
                'sla_status',
                'incident_opened_at',
                'incident_resolved_at',
            ]);
        });
    }
};
