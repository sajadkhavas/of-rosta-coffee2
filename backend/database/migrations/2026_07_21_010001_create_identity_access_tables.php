<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 80);
            $table->string('scope_type', 40)->default('global');
            $table->string('scope_id', 200)->default('global');
            $table->timestamps();

            $table->unique(['user_id', 'role', 'scope_type', 'scope_id']);
            $table->index(['role', 'scope_type', 'scope_id']);
        });

        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoke_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('mobile', 11);
            $table->string('purpose', 32);
            $table->char('code_digest', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at')->index();
            $table->timestamp('resend_available_at');
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->char('requested_ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['mobile', 'purpose', 'created_at']);
        });

        Schema::create('addresses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 80)->nullable();
            $table->string('recipient_name', 120);
            $table->char('recipient_mobile', 11);
            $table->string('province', 120);
            $table->string('city', 120);
            $table->string('address_line', 1000);
            $table->char('postal_code', 10)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('user_roles');
    }
};
