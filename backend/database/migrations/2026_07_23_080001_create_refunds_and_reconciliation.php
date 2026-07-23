<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
            $table->foreignUlid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 64);
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IRR');
            $table->string('idempotency_key', 160);
            $table->char('request_hash', 64);
            $table->text('reason');
            $table->string('provider_reference', 190)->nullable()->unique();
            $table->string('provider_code', 160)->nullable();
            $table->string('failure_code', 160)->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('processing_at')->nullable()->index();
            $table->timestamp('succeeded_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'idempotency_key']);
            $table->index(['payment_attempt_id', 'status', 'created_at']);
            $table->index(['order_id', 'status', 'created_at']);
        });

        Schema::create('financial_reconciliation_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->foreignUlid('refund_attempt_id')->nullable()->constrained('refund_attempts')->nullOnDelete();
            $table->foreignUlid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 80)->index();
            $table->string('status', 32)->index();
            $table->string('severity', 16)->default('high')->index();
            $table->char('deduplication_key', 64)->unique();
            $table->string('summary', 1000);
            $table->text('details')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('opened_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['order_id', 'status', 'created_at']);
            $table->index(['kind', 'status', 'severity']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('refunded_at')->nullable()->index()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('refunded_at');
        });

        Schema::dropIfExists('financial_reconciliation_cases');
        Schema::dropIfExists('refund_attempts');
    }
};
