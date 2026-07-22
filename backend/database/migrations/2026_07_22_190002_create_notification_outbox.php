<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key', 120)->unique();
            $table->string('channel', 32)->default('sms');
            $table->text('body');
            $table->string('provider_template', 160)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->nullable()->constrained('sub_orders')->cascadeOnDelete();
            $table->string('channel', 32)->default('sms');
            $table->text('destination');
            $table->string('template_key', 120);
            $table->text('payload');
            $table->string('status', 32)->index();
            $table->string('provider', 64)->nullable();
            $table->string('provider_message_id', 190)->nullable()->index();
            $table->string('deduplication_key', 190)->nullable()->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->index();
            $table->timestamp('processing_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'available_at', 'id']);
            $table->index(['order_id', 'created_at']);
            $table->index(['sub_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notification_templates');
    }
};
