<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('city', 120)->index();
            $table->string('address', 1000);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('instagram_handle', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('amenities')->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->foreignUlid('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'city', 'updated_at'], 'cafes_directory_idx');
            $table->index(['status', 'latitude', 'longitude'], 'cafes_geo_idx');
        });

        Schema::create('cafe_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('cafe_id')->constrained('cafes')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cafe_id', 'user_id']);
            $table->index(['user_id', 'is_active', 'cafe_id'], 'cafe_membership_access_idx');
        });

        Schema::create('wholesale_price_tiers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('min_weight_grams');
            $table->unsignedBigInteger('unit_price');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_variant_id', 'min_weight_grams'], 'wholesale_variant_weight_unique');
            $table->index(['product_variant_id', 'is_active', 'min_weight_grams'], 'wholesale_resolve_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_price_tiers');
        Schema::dropIfExists('cafe_memberships');
        Schema::dropIfExists('cafes');
    }
};
