<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roastery_settlement_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->unique()->constrained('roasteries')->cascadeOnDelete();
            $table->string('entity_type', 24);
            $table->text('legal_name');
            $table->text('account_holder_name');
            $table->text('iban');
            $table->char('iban_last4', 4);
            $table->string('status', 32)->default('pending_review')->index();
            $table->foreignUlid('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->foreignUlid('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roastery_settlement_profiles');
    }
};
