<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_upload_intents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('disk', 64);
            $table->string('object_key', 512)->unique();
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('status', 32)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['roastery_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_upload_intents');
    }
};
