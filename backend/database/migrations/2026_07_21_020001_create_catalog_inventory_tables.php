<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->char('country_code', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('roasteries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('city', 120)->nullable();
            $table->text('description')->default('');
            $table->text('shipping_policy')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('verified_at')->nullable()->index();
            $table->unsignedSmallInteger('preparation_min_hours')->nullable();
            $table->unsignedSmallInteger('preparation_max_hours')->nullable();
            $table->decimal('rating_value', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->string('alt', 300)->default('');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->longText('blur_data_url')->nullable();
            $table->json('sources');
            $table->timestamps();
            $table->index(['roastery_id', 'created_at']);
        });

        Schema::table('roasteries', function (Blueprint $table): void {
            $table->foreignUlid('logo_media_id')->nullable()->after('rating_count')
                ->constrained('media_assets')->nullOnDelete();
            $table->foreignUlid('cover_media_id')->nullable()->after('logo_media_id')
                ->constrained('media_assets')->nullOnDelete();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->foreignUlid('origin_id')->constrained('origins')->restrictOnDelete();
            $table->foreignUlid('primary_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('name', 240);
            $table->string('slug', 180)->unique();
            $table->string('short_description', 1000)->nullable();
            $table->longText('description')->default('');
            $table->string('processing_method', 32);
            $table->string('roast_level', 32);
            $table->unsignedTinyInteger('arabica_percentage')->default(100);
            $table->json('tasting_notes');
            $table->json('brewing_suggestions');
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->index(['roastery_id', 'status', 'updated_at']);
            $table->index(['origin_id', 'status']);
            $table->index(['processing_method', 'roast_level', 'status']);
        });

        Schema::create('product_media', function (Blueprint $table): void {
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['product_id', 'media_asset_id']);
            $table->index(['product_id', 'position']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 120)->unique();
            $table->unsignedSmallInteger('weight_grams');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->char('currency', 3)->default('IRR');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('stock_reserved')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'weight_grams']);
            $table->index(['product_id', 'is_active', 'price']);
        });

        Schema::create('roast_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_code', 120);
            $table->timestamp('roasted_at')->index();
            $table->timestamp('available_from')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_id', 'batch_code']);
            $table->index(['product_id', 'is_active', 'roasted_at']);
        });

        Schema::create('stock_ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUlid('roast_batch_id')->nullable()->constrained('roast_batches')->nullOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('delta');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 64);
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 200)->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['variant_id', 'idempotency_key']);
            $table->index(['variant_id', 'created_at']);
            $table->index(['roast_batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger_entries');
        Schema::dropIfExists('roast_batches');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('products');
        Schema::table('roasteries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cover_media_id');
            $table->dropConstrainedForeignId('logo_media_id');
        });
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('roasteries');
        Schema::dropIfExists('origins');
    }
};
