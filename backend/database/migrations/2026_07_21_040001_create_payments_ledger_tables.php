<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider', 80);
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IRR');
            $table->string('idempotency_key', 160);
            $table->char('request_hash', 64);
            $table->string('redirect_url', 2000)->nullable();
            $table->string('provider_authority', 255)->nullable();
            $table->string('provider_transaction_id', 255)->nullable();
            $table->string('failure_code', 160)->nullable();
            $table->string('failure_message', 1000)->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamp('initiated_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamp('refunded_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['provider', 'provider_authority']);
            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['order_id', 'status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_attempt_id')->constrained('payment_attempts')->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->string('provider_event_id', 255)->nullable();
            $table->char('payload_hash', 64);
            $table->longText('payload')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->unique(['payment_attempt_id', 'provider_event_id']);
            $table->index(['payment_attempt_id', 'occurred_at']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUlid('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->string('type', 48)->index();
            $table->string('reference_key', 200)->unique();
            $table->char('currency', 3)->default('IRR');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
            $table->foreignUlid('roastery_id')->nullable()->constrained('roasteries')->nullOnDelete();
            $table->string('account', 64)->index();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->char('currency', 3)->default('IRR');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['roastery_id', 'account', 'created_at']);
            $table->index(['transaction_id', 'account']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IRR');
            $table->string('reason', 1000);
            $table->string('provider_refund_id', 255)->nullable();
            $table->string('idempotency_key', 160);
            $table->char('request_hash', 64);
            $table->json('provider_metadata')->nullable();
            $table->timestamp('refunded_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['payment_attempt_id', 'idempotency_key']);
            $table->unique(['payment_attempt_id', 'provider_refund_id']);
        });

        Schema::create('settlement_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('status', 32)->index();
            $table->char('currency', 3)->default('IRR');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settlement_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('settlement_batch_id')->constrained('settlement_batches')->cascadeOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IRR');
            $table->json('statement_snapshot');
            $table->timestamps();
            $table->unique(['settlement_batch_id', 'roastery_id']);
        });

        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 32)->index();
            $table->string('code', 160)->index();
            $table->string('message', 2000);
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
    }
};
