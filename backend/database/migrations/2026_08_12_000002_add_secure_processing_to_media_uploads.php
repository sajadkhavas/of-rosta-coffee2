<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_upload_intents', function (Blueprint $table): void {
            $table->unsignedTinyInteger('processing_attempts')->default(0);
            $table->string('alt_text', 300)->nullable();
            $table->string('detected_mime_type', 120)->nullable();
            $table->unsignedBigInteger('actual_size_bytes')->nullable();
            $table->char('actual_checksum_sha256', 64)->nullable();
            $table->unsignedInteger('detected_width')->nullable();
            $table->unsignedInteger('detected_height')->nullable();
            $table->string('variant_version', 32)->nullable();
            $table->boolean('failure_retryable')->default(false)->index();
            $table->timestamp('processing_started_at')->nullable()->index();
            $table->timestamp('ready_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable()->index();
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('variant_version', 32)->nullable()->index();
        });

        DB::table('media_upload_intents')
            ->where('status', 'pending')
            ->update(['status' => 'uploading']);
        DB::table('media_upload_intents')
            ->where('status', 'completed')
            ->update([
                'status' => 'ready',
                'ready_at' => DB::raw('completed_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('media_upload_intents')
            ->whereIn('status', ['uploading', 'processing'])
            ->update(['status' => 'pending']);
        DB::table('media_upload_intents')
            ->where('status', 'ready')
            ->update(['status' => 'completed']);
        DB::table('media_upload_intents')
            ->where('status', 'rejected')
            ->update(['status' => 'failed']);

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn('variant_version');
        });

        Schema::table('media_upload_intents', function (Blueprint $table): void {
            $table->dropColumn([
                'processing_attempts',
                'alt_text',
                'detected_mime_type',
                'actual_size_bytes',
                'actual_checksum_sha256',
                'detected_width',
                'detected_height',
                'variant_version',
                'failure_retryable',
                'processing_started_at',
                'ready_at',
                'rejected_at',
            ]);
        });
    }
};
