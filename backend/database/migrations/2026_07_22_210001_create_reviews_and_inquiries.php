<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUlid('order_item_id')->unique()->constrained('order_items')->restrictOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body');
            $table->string('status', 32)->index();
            $table->boolean('is_verified_purchase')->default(true)->index();
            $table->foreignUlid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->index();
            $table->string('moderation_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['roastery_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('inquiries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 64)->index();
            $table->string('name', 160);
            $table->text('mobile')->nullable();
            $table->text('email')->nullable();
            $table->string('order_number', 120)->nullable()->index();
            $table->text('message');
            $table->string('status', 32)->index();
            $table->char('ip_hmac', 64)->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->char('duplicate_hash', 64)->index();
            $table->char('deduplication_key', 64)->unique();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['duplicate_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('reviews');
    }
};
