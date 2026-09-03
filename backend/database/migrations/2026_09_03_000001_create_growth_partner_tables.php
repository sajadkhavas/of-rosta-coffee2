<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_partners', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->string('channel', 32)->default('field_sales');
            $table->string('status', 24)->default('pending')->index();
            $table->string('display_name')->nullable();
            $table->string('terms_version')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignUlid('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_leads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('partner_id')->constrained('growth_partners')->cascadeOnDelete();
            $table->string('type', 24)->index();
            $table->string('status', 24)->default('lead')->index();
            $table->char('dedupe_hash', 64)->unique();
            $table->text('name')->nullable();
            $table->text('mobile')->nullable();
            $table->text('email')->nullable();
            $table->text('company_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('converted_roastery_id')->nullable()->constrained('roasteries')->nullOnDelete();
            $table->foreignUlid('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'type', 'status']);
        });

        Schema::create('partner_attributions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('partner_id')->constrained('growth_partners')->cascadeOnDelete();
            $table->foreignUlid('lead_id')->nullable()->constrained('growth_leads')->nullOnDelete();
            $table->string('subject_type', 32);
            $table->string('subject_id', 64);
            $table->string('source', 32)->default('lead');
            $table->timestamp('attributed_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id'], 'partner_attribution_subject_unique');
            $table->index(['partner_id', 'subject_type']);
        });

        Schema::create('partner_commission_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('version', 64)->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->string('basis', 32)->default('platform_revenue');
            $table->unsignedInteger('basis_points')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_commission_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('partner_id')->constrained('growth_partners')->cascadeOnDelete();
            $table->foreignUlid('attribution_id')->nullable()->constrained('partner_attributions')->nullOnDelete();
            $table->foreignUlid('policy_id')->constrained('partner_commission_policies')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('entry_type', 24)->default('accrual')->index();
            $table->foreignUlid('reversal_of_id')->nullable()->constrained('partner_commission_entries')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('attributed_gmv_amount')->default(0);
            $table->unsignedBigInteger('platform_revenue_amount')->default(0);
            $table->bigInteger('commission_amount');
            $table->string('currency', 8)->default('IRR');
            $table->string('idempotency_key', 191)->unique();
            $table->string('source_type', 64);
            $table->string('source_id', 191);
            $table->json('financial_snapshot');
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('recorded_at');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'partner_commission_source_unique');
            $table->index(['partner_id', 'order_id']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('partner_commission_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_type', 32);
            $table->string('source_id', 64);
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('refund_attempt_id')->nullable()->constrained('refund_attempts')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('last_error_code', 128)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->unique(['event_type', 'source_id'], 'partner_commission_events_source_unique');
            $table->index(['status', 'available_at'], 'partner_commission_events_due_idx');
            $table->index('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_commission_events');
        Schema::dropIfExists('partner_commission_entries');
        Schema::dropIfExists('partner_commission_policies');
        Schema::dropIfExists('partner_attributions');
        Schema::dropIfExists('growth_leads');
        Schema::dropIfExists('growth_partners');
    }
};
