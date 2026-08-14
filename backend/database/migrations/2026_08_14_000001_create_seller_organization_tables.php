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
        Schema::table('roasteries', function (Blueprint $table): void {
            $table->string('timezone', 80)->default('Asia/Tehran')->after('city');
        });

        Schema::create('roastery_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignUlid('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['roastery_id', 'user_id']);
            $table->index(['user_id', 'is_locked', 'roastery_id']);
        });

        Schema::create('roastery_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->foreignUlid('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('target_mobile');
            $table->char('target_mobile_hash', 64)->index();
            $table->char('token_hash', 64)->unique();
            $table->string('role', 32);
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignUlid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['roastery_id', 'target_mobile_hash', 'expires_at']);
        });

        Schema::create('roastery_weekly_hours', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->char('opens_at', 5)->nullable();
            $table->char('closes_at', 5)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
            $table->unique(['roastery_id', 'weekday']);
        });

        Schema::create('roastery_schedule_exceptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->date('local_date');
            $table->boolean('is_closed')->default(true);
            $table->char('opens_at', 5)->nullable();
            $table->char('closes_at', 5)->nullable();
            $table->string('public_reason', 180)->nullable();
            $table->timestamps();
            $table->unique(['roastery_id', 'local_date']);
        });

        Schema::create('roastery_closures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('public_reason', 180)->nullable();
            $table->boolean('blocks_new_orders')->default(true)->index();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignUlid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['roastery_id', 'revoked_at', 'starts_at', 'ends_at']);
        });

        Schema::create('roastery_promotions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['roastery_id', 'status', 'starts_at', 'ends_at']);
        });

        $now = now();
        foreach ([
            'roastery_staff' => 'catalog',
            'roastery_manager' => 'manager',
            'roastery_owner' => 'owner',
        ] as $legacyRole => $membershipRole) {
            foreach (DB::table('user_roles')
                ->where('scope_type', 'roastery')
                ->where('role', $legacyRole)
                ->get(['user_id', 'scope_id']) as $assignment) {
                if (! DB::table('roasteries')->where('id', $assignment->scope_id)->exists()) {
                    continue;
                }

                $membership = DB::table('roastery_memberships')
                    ->where('roastery_id', $assignment->scope_id)
                    ->where('user_id', $assignment->user_id);
                if ($membership->exists()) {
                    $membership->update([
                        'role' => $membershipRole,
                        'is_locked' => false,
                        'updated_at' => $now,
                    ]);
                    continue;
                }

                DB::table('roastery_memberships')->insert([
                    'id' => (string) Str::ulid(),
                    'roastery_id' => $assignment->scope_id,
                    'user_id' => $assignment->user_id,
                    'role' => $membershipRole,
                    'is_locked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ([
            'seller.invite' => 'دعوت عضویت در روستری {{roastery_name}} در رستا. کد امن دعوت: {{invite_token}}',
            'seller.closure.started' => 'تعطیلی موقت {{roastery_name}} ثبت شد و تا {{ends_at}} ادامه دارد.',
            'seller.closure.ended' => 'تعطیلی موقت {{roastery_name}} پایان یافت و پذیرش سفارش طبق برنامه روستری ادامه دارد.',
        ] as $key => $body) {
            $template = DB::table('notification_templates')->where('key', $key);
            $values = [
                'channel' => 'sms',
                'body' => $body,
                'provider_template' => null,
                'is_active' => true,
                'updated_at' => $now,
            ];
            if ($template->exists()) {
                $template->update($values);
                continue;
            }

            DB::table('notification_templates')->insert([
                'id' => (string) Str::ulid(),
                'key' => $key,
                ...$values,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('key', [
            'seller.invite',
            'seller.closure.started',
            'seller.closure.ended',
        ])->delete();
        Schema::dropIfExists('roastery_promotions');
        Schema::dropIfExists('roastery_closures');
        Schema::dropIfExists('roastery_schedule_exceptions');
        Schema::dropIfExists('roastery_weekly_hours');
        Schema::dropIfExists('roastery_invitations');
        Schema::dropIfExists('roastery_memberships');
        Schema::table('roasteries', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
