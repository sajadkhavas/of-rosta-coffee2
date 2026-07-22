<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_authors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('bio')->nullable();
            $table->json('credentials');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('content_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('author_id')
                ->nullable()
                ->constrained('content_authors')
                ->nullOnDelete();
            $table->foreignUlid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('title', 240);
            $table->string('slug', 180)->unique();
            $table->string('canonical_path', 500)->unique();
            $table->string('excerpt', 1000)->nullable();
            $table->json('body');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->boolean('robots_index')->default(false)->index();
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title', 180)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_media_url', 2000)->nullable();
            $table->string('schema_type', 80)->default('Article');
            $table->json('keywords');
            $table->char('content_hash', 64);
            $table->timestamps();
            $table->index(['type', 'status', 'published_at']);
            $table->index(['robots_index', 'status', 'published_at']);
        });

        Schema::create('content_relations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('content_entry_id')
                ->constrained('content_entries')
                ->cascadeOnDelete();
            $table->string('relation_type', 80);
            $table->string('target_type', 80);
            $table->string('target_key', 240);
            $table->string('anchor_text', 300)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique([
                'content_entry_id',
                'relation_type',
                'target_type',
                'target_key',
            ], 'content_relations_unique_target');
            $table->index(['content_entry_id', 'position']);
            $table->index(['target_type', 'target_key']);
        });

        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('source_path', 500)->unique();
            $table->string('destination_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('content_relations');
        Schema::dropIfExists('content_entries');
        Schema::dropIfExists('content_authors');
    }
};
