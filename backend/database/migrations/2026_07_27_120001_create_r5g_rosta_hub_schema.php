<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rosta_hubs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('name', 180);
            $table->string('province', 120);
            $table->string('city', 120);
            $table->string('fee_mode', 16)->default('free');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedInteger('preparation_minutes')->default(0);
            $table->unsignedInteger('capacity_per_day')->nullable();
            $table->json('supported_weights');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();

            $table->index(['province', 'city', 'is_active']);
        });

        Schema::create('rosta_hub_service_zones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('hub_id')->constrained('rosta_hubs')->cascadeOnDelete();
            $table->string('province', 120);
            $table->string('city', 120);
            $table->unsignedBigInteger('inbound_shipping_fee')->default(0);
            $table->unsignedBigInteger('outbound_shipping_fee')->default(0);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();

            $table->unique(['hub_id', 'province', 'city']);
            $table->index(
                ['province', 'city', 'is_active', 'priority'],
                'rosta_hub_zone_lookup',
            );
        });

        Schema::create('rosta_hub_grinding_profile', function (Blueprint $table): void {
            $table->foreignUlid('hub_id')->constrained('rosta_hubs')->cascadeOnDelete();
            $table->foreignUlid('grinding_profile_id')
                ->constrained('grinding_profiles')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['hub_id', 'grinding_profile_id']);
        });

        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->index('quote_item_id', 'r5g_quote_item_fk_support');
        });
        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->dropUnique('checkout_quote_item_services_quote_item_id_service_type_unique');
            $table->foreignUlid('provider_hub_id')
                ->nullable()
                ->after('provider_roastery_id')
                ->constrained('rosta_hubs')
                ->nullOnDelete();
            $table->unique(
                ['quote_item_id', 'service_type', 'provider_type'],
                'quote_item_service_provider_unique',
            );
        });
        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->dropIndex('r5g_quote_item_fk_support');
        });

        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->index('order_item_id', 'r5g_order_item_fk_support');
        });
        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->dropUnique('order_item_services_order_item_id_service_type_unique');
            $table->foreignUlid('provider_hub_id')
                ->nullable()
                ->after('provider_roastery_id')
                ->constrained('rosta_hubs')
                ->nullOnDelete();
            $table->unique(
                ['order_item_id', 'service_type', 'provider_type'],
                'order_item_service_provider_unique',
            );
        });
        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->dropIndex('r5g_order_item_fk_support');
        });
    }

    public function down(): void
    {
        $hasHubQuoteServices = DB::table('checkout_quote_item_services')
            ->where(static fn ($query) => $query
                ->whereNotNull('provider_hub_id')
                ->orWhere('provider_type', 'rosta_hub'))
            ->exists();
        $hasHubOrderServices = DB::table('order_item_services')
            ->where(static fn ($query) => $query
                ->whereNotNull('provider_hub_id')
                ->orWhere('provider_type', 'rosta_hub'))
            ->exists();

        if ($hasHubQuoteServices || $hasHubOrderServices) {
            throw new \RuntimeException(
                'R5G rollback refused because Rosta Hub service snapshots already exist.',
            );
        }

        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->index('order_item_id', 'r5g_order_item_fk_support');
        });
        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->dropUnique('order_item_service_provider_unique');
            $table->dropConstrainedForeignId('provider_hub_id');
            $table->unique(['order_item_id', 'service_type']);
        });
        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->dropIndex('r5g_order_item_fk_support');
        });

        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->index('quote_item_id', 'r5g_quote_item_fk_support');
        });
        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->dropUnique('quote_item_service_provider_unique');
            $table->dropConstrainedForeignId('provider_hub_id');
            $table->unique(['quote_item_id', 'service_type']);
        });
        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->dropIndex('r5g_quote_item_fk_support');
        });

        Schema::dropIfExists('rosta_hub_grinding_profile');
        Schema::dropIfExists('rosta_hub_service_zones');
        Schema::dropIfExists('rosta_hubs');
    }
};
