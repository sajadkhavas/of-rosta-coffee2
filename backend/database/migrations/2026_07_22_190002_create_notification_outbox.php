<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        $now = now();
        $templates = [
            'order.paid' => 'پرداخت سفارش {{order_number}} در رستا تأیید شد. روستری {{roastery_name}} سفارش را بررسی می‌کند.',
            'order.accepted' => 'سفارش {{order_number}} توسط {{roastery_name}} پذیرفته شد.',
            'order.rejected' => 'سفارش {{order_number}} توسط روستری پذیرفته نشد. وضعیت بازپرداخت را از حساب خود پیگیری کنید.',
            'order.preparing' => 'آماده‌سازی سفارش {{order_number}} در {{roastery_name}} شروع شد.',
            'order.ready_to_ship' => 'سفارش {{order_number}} آماده ارسال است.',
            'order.shipped' => 'سفارش {{order_number}} ارسال شد. کد رهگیری: {{tracking_code}}',
            'order.delivered' => 'سفارش {{order_number}} تحویل شد. از همراهی شما با رستا سپاسگزاریم.',
            'order.cancelled' => 'سفارش {{order_number}} لغو شد. جزئیات در حساب کاربری قابل مشاهده است.',
            'order.refunded' => 'بازپرداخت سفارش {{order_number}} ثبت شد. شناسه پیگیری: {{refund_reference}}',
        ];

        DB::table('notification_templates')->insert(array_map(
            static fn (string $key, string $body): array => [
                'id' => (string) Str::ulid(),
                'key' => $key,
                'channel' => 'sms',
                'body' => $body,
                'provider_template' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys($templates),
            array_values($templates),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notification_templates');
    }
};
