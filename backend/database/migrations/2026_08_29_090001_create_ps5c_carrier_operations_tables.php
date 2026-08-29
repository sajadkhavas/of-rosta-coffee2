<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_webhook_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_id', 160)->unique();
            $table->foreignUlid('shipment_leg_id')->constrained('shipment_legs')->restrictOnDelete();
            $table->string('carrier', 120);
            $table->string('tracking_code', 200);
            $table->string('event_type', 40);
            $table->timestamp('occurred_at');
            $table->char('payload_hash', 64);
            $table->string('signature_version', 16)->default('v1');
            $table->timestamp('received_at');
            $table->index(['carrier', 'tracking_code']);
            $table->index(['shipment_leg_id', 'occurred_at']);
        });

        Schema::create('failed_job_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('failed_job_uuid', 255)->index();
            $table->string('action', 24);
            $table->string('status', 24)->default('pending');
            $table->string('reason', 1000);
            $table->foreignUlid('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('confirmed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['failed_job_uuid', 'action', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_job_operations');
        Schema::dropIfExists('carrier_webhook_receipts');
    }
};
