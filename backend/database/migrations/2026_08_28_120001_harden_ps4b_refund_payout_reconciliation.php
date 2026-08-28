<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_batches', function (Blueprint $table): void {
            $table->foreignUlid('confirmed_by_id')
                ->nullable()
                ->after('processed_by_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('payout_method', 32)->nullable()->after('payout_reference');
            $table->char('payout_evidence_hash', 64)->nullable()->after('payout_method')->index();
            $table->longText('payout_evidence')->nullable()->after('payout_evidence_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settlement_batches')) {
            return;
        }

        if (
            DB::table('settlement_batches')->whereNotNull('confirmed_by_id')->exists()
            || DB::table('settlement_batches')->whereNotNull('payout_evidence_hash')->exists()
            || DB::table('settlement_batches')->whereNotNull('payout_evidence')->exists()
        ) {
            throw new RuntimeException('PS4.2 rollback refused because payout confirmation evidence exists.');
        }

        Schema::table('settlement_batches', function (Blueprint $table): void {
            $table->dropForeign(['confirmed_by_id']);
            $table->dropIndex(['payout_evidence_hash']);
            $table->dropColumn([
                'confirmed_by_id',
                'payout_method',
                'payout_evidence_hash',
                'payout_evidence',
            ]);
        });
    }
};
