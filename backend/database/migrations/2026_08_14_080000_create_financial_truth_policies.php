<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->string('currency', 3)->default('IRR');
            $table->string('rounding_mode', 24)->default('half_up');
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_to')->nullable()->index();
            $table->ulid('created_by');
            $table->ulid('submitted_by')->nullable();
            $table->ulid('published_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->text('change_reason');
            $table->timestamps();
            $table->index(['status', 'currency', 'effective_from', 'effective_to'], 'tax_policy_active_lookup');
        });

        Schema::create('tax_policy_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tax_policy_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('component', 24);
            $table->string('jurisdiction', 64);
            $table->unsignedInteger('rate_basis_points');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('applicability')->nullable();
            $table->timestamps();
            $table->unique(['tax_policy_id', 'code']);
            $table->index(['tax_policy_id', 'component', 'priority']);
        });

        Schema::create('commission_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->string('currency', 3)->default('IRR');
            $table->string('rounding_mode', 24)->default('half_up');
            $table->timestamp('effective_from')->nullable()->index();
            $table->timestamp('effective_to')->nullable()->index();
            $table->ulid('created_by');
            $table->ulid('submitted_by')->nullable();
            $table->ulid('published_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->text('change_reason');
            $table->timestamps();
            $table->index(['status', 'currency', 'effective_from', 'effective_to'], 'commission_policy_active_lookup');
        });

        Schema::create('commission_policy_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('commission_policy_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('component', 24);
            $table->string('owner_type', 24)->nullable();
            $table->unsignedInteger('rate_basis_points');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('applicability')->nullable();
            $table->timestamps();
            $table->unique(['commission_policy_id', 'code']);
            $table->index(['commission_policy_id', 'component', 'priority'], 'commission_rule_lookup');
        });

        Schema::table('checkout_quotes', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('commission_total')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('checkout_quote_groups', function (Blueprint $table): void {
            $table->unsignedBigInteger('commission_total')->default(0);
            $table->unsignedBigInteger('payable_total')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('checkout_quote_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('checkout_quote_item_services', function (Blueprint $table): void {
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('commission_total')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('order_item_services', function (Blueprint $table): void {
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('settlement_allocations', function (Blueprint $table): void {
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->json('financial_snapshot')->nullable();
        });
        Schema::table('order_tax_lines', function (Blueprint $table): void {
            $table->foreignUlid('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component', 24)->nullable();
            $table->unsignedInteger('policy_version')->nullable();
        });

        $legacy = json_encode([
            'status' => 'legacy_unknown',
            'version' => 'legacy-pre-ps4a',
            'reason' => 'No authoritative policy snapshot existed when this row was created.',
        ], JSON_UNESCAPED_SLASHES);

        foreach ([
            'checkout_quotes',
            'checkout_quote_groups',
            'checkout_quote_items',
            'checkout_quote_item_services',
            'orders',
            'sub_orders',
            'order_items',
            'order_item_services',
            'settlement_allocations',
        ] as $table) {
            DB::table($table)->whereNull('financial_snapshot')->update(['financial_snapshot' => $legacy]);
        }

        DB::table('checkout_quote_groups')->update([
            'payable_total' => DB::raw('grand_total'),
        ]);
        DB::table('checkout_quote_items')->update([
            'net_amount' => DB::raw('line_total'),
        ]);
        DB::table('order_items')->update([
            'net_amount' => DB::raw('line_total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_tax_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn(['component', 'policy_version']);
        });
        Schema::table('settlement_allocations', fn (Blueprint $table) => $table->dropColumn(['commission_amount', 'financial_snapshot']));
        Schema::table('order_item_services', fn (Blueprint $table) => $table->dropColumn(['commission_amount', 'financial_snapshot']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'tax_amount', 'commission_amount', 'net_amount', 'financial_snapshot']));
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn('financial_snapshot'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['tax_total', 'commission_total', 'financial_snapshot']));
        Schema::table('checkout_quote_item_services', fn (Blueprint $table) => $table->dropColumn(['commission_amount', 'financial_snapshot']));
        Schema::table('checkout_quote_items', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'tax_amount', 'commission_amount', 'net_amount', 'financial_snapshot']));
        Schema::table('checkout_quote_groups', fn (Blueprint $table) => $table->dropColumn(['commission_total', 'payable_total', 'financial_snapshot']));
        Schema::table('checkout_quotes', fn (Blueprint $table) => $table->dropColumn(['tax_total', 'commission_total', 'financial_snapshot']));
        Schema::dropIfExists('commission_policy_rules');
        Schema::dropIfExists('commission_policies');
        Schema::dropIfExists('tax_policy_rules');
        Schema::dropIfExists('tax_policies');
    }
};
