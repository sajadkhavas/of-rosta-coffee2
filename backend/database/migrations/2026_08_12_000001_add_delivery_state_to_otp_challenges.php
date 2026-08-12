<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_challenges', function (Blueprint $table): void {
            $table->string('delivery_status', 24)->default('pending')->index();
            $table->unsignedTinyInteger('delivery_attempts')->default(0);
            $table->string('delivery_provider', 32)->nullable();
            $table->string('provider_message_id', 190)->nullable()->index();
            $table->string('delivery_error_code', 80)->nullable();
            $table->timestamp('delivery_started_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('delivery_failed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('otp_challenges', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_status',
                'delivery_attempts',
                'delivery_provider',
                'provider_message_id',
                'delivery_error_code',
                'delivery_started_at',
                'delivered_at',
                'delivery_failed_at',
            ]);
        });
    }
};
