<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_quotes', function (Blueprint $table): void {
            $table->ulid('roastery_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->ulid('roastery_id')->nullable()->change();
        });

        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->dropUnique('sub_orders_order_id_unique');
            $table->index(['order_id', 'status'], 'sub_orders_order_status_index');
            $table->string('acceptance_status', 48)
                ->default('awaiting_roastery_acceptance')
                ->index()
                ->after('status');
            $table->unsignedBigInteger('packaging_total')->default(0)->after('shipping_total');
            $table->unsignedBigInteger('grinding_total')->default(0)->after('packaging_total');
            $table->unsignedBigInteger('discount_total')->default(0)->after('grinding_total');
            $table->unsignedBigInteger('tax_total')->default(0)->after('discount_total');
            $table->unsignedBigInteger('grand_total')->default(0)->after('tax_total');
            $table->unsignedBigInteger('commission_total')->default(0)->after('grand_total');
            $table->unsignedBigInteger('payable_total')->default(0)->after('commission_total');
            $table->char('currency', 3)->default('IRR')->after('payable_total');
            $table->timestamp('customer_cancelled_at')->nullable()->index()->after('cancelled_at');
            $table->string('rejection_code', 120)->nullable()->index()->after('rejection_reason');
            $table->string('cancellation_code', 120)->nullable()->index()->after('rejection_code');
        });

        $this->backfillLegacySubOrders();

        Schema::create('checkout_quote_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('quote_id')->constrained('checkout_quotes')->cascadeOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('packaging_total')->default(0);
            $table->unsignedBigInteger('grinding_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->char('currency', 3)->default('IRR');
            $table->json('pricing_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['quote_id', 'roastery_id']);
            $table->index(['roastery_id', 'created_at']);
        });

        Schema::table('checkout_quote_items', function (Blueprint $table): void {
            $table->foreignUlid('group_id')
                ->nullable()
                ->after('quote_id')
                ->constrained('checkout_quote_groups')
                ->cascadeOnDelete();
            $table->index(['group_id', 'created_at']);
        });

        $this->backfillLegacyQuoteGroups();

        Schema::create('grinding_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 100);
            $table->unsignedSmallInteger('version');
            $table->string('public_name', 160);
            $table->string('brew_method', 100)->index();
            $table->json('recipe_snapshot');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        Schema::create('roastery_grinding_capabilities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->unique()->constrained('roasteries')->cascadeOnDelete();
            $table->string('availability', 32)->default('unavailable')->index();
            $table->string('fee_mode', 16)->default('free');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedInteger('preparation_minutes')->default(0);
            $table->unsignedInteger('capacity_per_day')->nullable();
            $table->json('supported_weights');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('roastery_grinding_profile', function (Blueprint $table): void {
            $table->foreignUlid('capability_id')
                ->constrained('roastery_grinding_capabilities')
                ->cascadeOnDelete();
            $table->foreignUlid('grinding_profile_id')
                ->constrained('grinding_profiles')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['capability_id', 'grinding_profile_id']);
        });

        Schema::create('checkout_quote_item_services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('quote_group_id')
                ->constrained('checkout_quote_groups')
                ->cascadeOnDelete();
            $table->foreignUlid('quote_item_id')
                ->constrained('checkout_quote_items')
                ->cascadeOnDelete();
            $table->string('service_type', 48);
            $table->string('provider_type', 32);
            $table->foreignUlid('provider_roastery_id')
                ->nullable()
                ->constrained('roasteries')
                ->nullOnDelete();
            $table->foreignUlid('grinding_profile_id')
                ->nullable()
                ->constrained('grinding_profiles')
                ->restrictOnDelete();
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->unsignedBigInteger('packaging_fee')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->char('currency', 3)->default('IRR');
            $table->json('pricing_snapshot');
            $table->json('service_snapshot');
            $table->timestamps();

            $table->unique(['quote_item_id', 'service_type']);
            $table->index(['quote_group_id', 'provider_type']);
        });

        Schema::create('order_item_services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('service_type', 48);
            $table->string('provider_type', 32);
            $table->foreignUlid('provider_roastery_id')
                ->nullable()
                ->constrained('roasteries')
                ->nullOnDelete();
            $table->foreignUlid('grinding_profile_id')
                ->nullable()
                ->constrained('grinding_profiles')
                ->restrictOnDelete();
            $table->string('status', 48)->default('requested')->index();
            $table->unsignedBigInteger('service_fee')->default(0);
            $table->unsignedBigInteger('packaging_fee')->default(0);
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->char('currency', 3)->default('IRR');
            $table->json('pricing_snapshot');
            $table->json('service_snapshot');
            $table->string('failure_code', 160)->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['order_item_id', 'service_type']);
            $table->index(['sub_order_id', 'status', 'created_at']);
            $table->index(['provider_type', 'status', 'created_at']);
        });

        Schema::create('shipment_legs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')
                ->nullable()
                ->constrained('sub_orders')
                ->cascadeOnDelete();
            $table->foreignUlid('order_item_service_id')
                ->nullable()
                ->constrained('order_item_services')
                ->cascadeOnDelete();
            $table->foreignUlid('legacy_shipment_id')
                ->nullable()
                ->unique()
                ->constrained('shipments')
                ->nullOnDelete();
            $table->string('route_type', 48)->index();
            $table->unsignedSmallInteger('sequence');
            $table->string('status', 48)->default('planned')->index();
            $table->string('carrier', 120)->nullable();
            $table->string('tracking_code', 200)->nullable()->index();
            $table->string('charge_owner_type', 48);
            $table->string('charge_owner_id', 64)->nullable();
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->char('currency', 3)->default('IRR');
            $table->longText('origin_snapshot')->nullable();
            $table->longText('destination_snapshot')->nullable();
            $table->timestamp('planned_at')->nullable()->index();
            $table->timestamp('picked_up_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
            $table->index(['sub_order_id', 'status', 'created_at']);
        });

        Schema::create('settlement_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')
                ->nullable()
                ->constrained('sub_orders')
                ->cascadeOnDelete();
            $table->foreignUlid('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->foreignUlid('order_item_service_id')
                ->nullable()
                ->constrained('order_item_services')
                ->cascadeOnDelete();
            $table->foreignUlid('shipment_leg_id')
                ->nullable()
                ->constrained('shipment_legs')
                ->cascadeOnDelete();
            $table->string('allocation_type', 48)->index();
            $table->string('owner_type', 48)->index();
            $table->string('owner_id', 64)->nullable()->index();
            $table->string('status', 32)->default('held')->index();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->char('currency', 3)->default('IRR');
            $table->string('tax_code', 100)->nullable()->index();
            $table->string('pricing_version', 100);
            $table->string('source_reference', 190);
            $table->string('idempotency_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('eligible_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('reversed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['order_id', 'status', 'created_at']);
            $table->index(['sub_order_id', 'allocation_type']);
            $table->index(['owner_type', 'owner_id', 'status']);
        });

        Schema::create('order_tax_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('settlement_allocation_id')
                ->constrained('settlement_allocations')
                ->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')
                ->nullable()
                ->constrained('sub_orders')
                ->cascadeOnDelete();
            $table->foreignUlid('order_item_service_id')
                ->nullable()
                ->constrained('order_item_services')
                ->cascadeOnDelete();
            $table->string('tax_code', 100)->index();
            $table->string('jurisdiction', 120)->nullable();
            $table->unsignedBigInteger('taxable_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedInteger('rate_basis_points')->nullable();
            $table->json('calculation_snapshot');
            $table->timestamps();

            $table->unique(['settlement_allocation_id', 'tax_code']);
            $table->index(['order_id', 'tax_code']);
        });

        Schema::create('order_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('sub_order_id')
                ->nullable()
                ->constrained('sub_orders')
                ->cascadeOnDelete();
            $table->foreignUlid('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->cascadeOnDelete();
            $table->foreignUlid('order_item_service_id')
                ->nullable()
                ->constrained('order_item_services')
                ->cascadeOnDelete();
            $table->foreignUlid('shipment_leg_id')
                ->nullable()
                ->constrained('shipment_legs')
                ->cascadeOnDelete();
            $table->string('event_type', 100)->index();
            $table->string('previous_state', 100)->nullable();
            $table->string('next_state', 100)->nullable();
            $table->string('actor_type', 48);
            $table->foreignUlid('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_reference', 190)->nullable();
            $table->string('request_id', 190)->nullable()->index();
            $table->string('reason_code', 160)->nullable()->index();
            $table->string('customer_title', 220);
            $table->text('customer_description')->nullable();
            $table->longText('internal_metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'occurred_at']);
            $table->index(['sub_order_id', 'occurred_at']);
            $table->index(['order_item_service_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('sub_orders')
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new \RuntimeException(
                'R5B rollback refused because multi-roastery orders already exist.',
            );
        }

        if (DB::table('orders')->whereNull('roastery_id')->exists()
            || DB::table('checkout_quotes')->whereNull('roastery_id')->exists()) {
            throw new \RuntimeException(
                'R5B rollback refused because parent orders or quotes no longer have a legacy roastery.',
            );
        }

        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_tax_lines');
        Schema::dropIfExists('settlement_allocations');
        Schema::dropIfExists('shipment_legs');
        Schema::dropIfExists('order_item_services');
        Schema::dropIfExists('checkout_quote_item_services');
        Schema::dropIfExists('roastery_grinding_profile');
        Schema::dropIfExists('roastery_grinding_capabilities');
        Schema::dropIfExists('grinding_profiles');

        Schema::table('checkout_quote_items', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id', 'created_at']);
            $table->dropColumn('group_id');
        });

        Schema::dropIfExists('checkout_quote_groups');

        Schema::table('sub_orders', function (Blueprint $table): void {
            $table->dropIndex('sub_orders_order_status_index');
            $table->dropColumn([
                'acceptance_status',
                'packaging_total',
                'grinding_total',
                'discount_total',
                'tax_total',
                'grand_total',
                'commission_total',
                'payable_total',
                'currency',
                'customer_cancelled_at',
                'rejection_code',
                'cancellation_code',
            ]);
            $table->unique('order_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->ulid('roastery_id')->nullable(false)->change();
        });

        Schema::table('checkout_quotes', function (Blueprint $table): void {
            $table->ulid('roastery_id')->nullable(false)->change();
        });
    }

    private function backfillLegacySubOrders(): void
    {
        DB::table('sub_orders')
            ->orderBy('id')
            ->chunkById(100, function ($subOrders): void {
                foreach ($subOrders as $subOrder) {
                    $order = DB::table('orders')->where('id', $subOrder->order_id)->first();

                    if ($order === null) {
                        throw new \RuntimeException(
                            "Sub-order {$subOrder->id} has no parent order.",
                        );
                    }

                    $acceptanceStatus = match (true) {
                        $subOrder->status === 'pending_acceptance' => 'awaiting_roastery_acceptance',
                        $subOrder->status === 'cancelled' => 'cancelled_by_customer',
                        $subOrder->status === 'rejected',
                        $subOrder->rejection_reason !== null => 'rejected_by_roastery',
                        default => 'accepted',
                    };

                    DB::table('sub_orders')
                        ->where('id', $subOrder->id)
                        ->update([
                            'acceptance_status' => $acceptanceStatus,
                            'discount_total' => $order->discount_total,
                            'grand_total' => $order->grand_total,
                            'payable_total' => max(
                                0,
                                (int) $order->grand_total,
                            ),
                            'currency' => $order->currency,
                            'customer_cancelled_at' => $acceptanceStatus === 'cancelled_by_customer'
                                ? $subOrder->cancelled_at
                                : null,
                            'updated_at' => now(),
                        ]);
                }
            }, 'id');
    }

    private function backfillLegacyQuoteGroups(): void
    {
        DB::table('checkout_quotes')
            ->orderBy('id')
            ->chunkById(100, function ($quotes): void {
                foreach ($quotes as $quote) {
                    if ($quote->roastery_id === null) {
                        throw new \RuntimeException(
                            "Legacy checkout quote {$quote->id} has no roastery.",
                        );
                    }

                    $groupId = (string) Str::ulid();

                    DB::table('checkout_quote_groups')->insert([
                        'id' => $groupId,
                        'quote_id' => $quote->id,
                        'roastery_id' => $quote->roastery_id,
                        'subtotal' => $quote->subtotal,
                        'packaging_total' => 0,
                        'grinding_total' => 0,
                        'shipping_total' => $quote->shipping_total,
                        'discount_total' => $quote->discount_total,
                        'tax_total' => 0,
                        'grand_total' => $quote->grand_total,
                        'currency' => $quote->currency,
                        'pricing_snapshot' => json_encode([
                            'source' => 'legacy_single_roastery_quote',
                            'migrated_at' => now()->toIso8601String(),
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $quote->created_at,
                        'updated_at' => now(),
                    ]);

                    DB::table('checkout_quote_items')
                        ->where('quote_id', $quote->id)
                        ->update([
                            'group_id' => $groupId,
                            'updated_at' => now(),
                        ]);
                }
            }, 'id');
    }
};
