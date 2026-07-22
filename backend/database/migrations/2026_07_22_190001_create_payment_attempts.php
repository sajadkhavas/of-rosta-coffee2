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
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 64);
            $table->unsignedSmallInteger('attempt_number');
            $table->string('idempotency_key', 160);
            $table->char('request_hash', 64);
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_provider');
            $table->char('currency', 3)->default('IRR');
            $table->string('authority', 190)->nullable()->unique();
            $table->string('reference_id', 190)->nullable()->index();
            $table->text('redirect_url')->nullable();
            $table->string('gateway_code', 100)->nullable();
            $table->string('failure_code', 160)->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('verification_payload')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('review_required_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['order_id', 'idempotency_key']);
            $table->unique(['order_id', 'attempt_number']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['order_id', 'status', 'expires_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->index()->after('placed_at');
        });

        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->timestamp('consumed_at')->nullable()->index()->after('released_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->dropColumn('consumed_at');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('paid_at');
        });

        Schema::dropIfExists('payment_attempts');
    }
};
