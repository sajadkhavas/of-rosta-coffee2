<?php

namespace App\Services\Seller;

use App\Enums\RoasteryMembershipRole;
use App\Enums\RoasteryPromotionStatus;
use App\Enums\Role;
use App\Enums\SellerPermission;
use App\Exceptions\ApiDomainException;
use App\Models\Roastery;
use App\Models\RoasteryClosure;
use App\Models\RoasteryInvitation;
use App\Models\RoasteryMembership;
use App\Models\RoasteryPromotion;
use App\Models\RoasteryScheduleException;
use App\Models\RoasteryWeeklyHour;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\IranMobile;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SellerOrganizationService
{
    public function __construct(
        private readonly SellerAccess $access,
        private readonly AuditRecorder $audit,
        private readonly SellerOrganizationNotifier $notifier,
    ) {}

    /** @return array{invite: RoasteryInvitation, token: string} */
    public function invite(
        User $actor,
        Roastery $roastery,
        string $mobile,
        RoasteryMembershipRole $role,
        Request $request,
    ): array {
        $this->access->assertPermission($actor, $roastery, SellerPermission::OrganizationManage);
        $actorRole = $this->access->effectiveRole($actor, $roastery->id);
        if ($actorRole === RoasteryMembershipRole::Manager && in_array(
            $role,
            [RoasteryMembershipRole::Owner, RoasteryMembershipRole::Manager],
            true,
        )) {
            throw new AuthorizationException;
        }

        $normalized = IranMobile::normalize($mobile);
        $mobileHash = hash('sha256', $normalized);
        $target = User::query()->where('mobile', $normalized)->first();
        $token = bin2hex(random_bytes(32));

        $invite = DB::transaction(function () use (
            $actor,
            $roastery,
            $role,
            $normalized,
            $mobileHash,
            $target,
            $token,
            $request,
        ): RoasteryInvitation {
            Roastery::query()->whereKey($roastery->id)->lockForUpdate()->firstOrFail();

            $pending = RoasteryInvitation::query()
                ->where('roastery_id', $roastery->id)
                ->where('target_mobile_hash', $mobileHash)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->exists();
            if ($pending) {
                throw new ApiDomainException(
                    'seller.invite_pending',
                    'برای این حساب یک دعوت فعال وجود دارد.',
                    409,
                );
            }

            if ($target instanceof User && RoasteryMembership::query()
                ->where('roastery_id', $roastery->id)
                ->where('user_id', $target->id)
                ->exists()) {
                throw new ApiDomainException(
                    'seller.membership_exists',
                    'این حساب از قبل عضو روستری است.',
                    409,
                );
            }

            $invite = RoasteryInvitation::query()->create([
                'roastery_id' => $roastery->id,
                'invited_by' => $actor->id,
                'target_user_id' => $target?->id,
                'target_mobile' => $normalized,
                'target_mobile_hash' => $mobileHash,
                'token_hash' => hash('sha256', $token),
                'role' => $role,
                'expires_at' => now()->addHours(72),
            ]);

            $this->notifier->queueMobile(
                $normalized,
                'seller.invite',
                [
                    'roastery_name' => (string) $roastery->name,
                    'invite_token' => $token,
                ],
                $target,
                'seller-invite:'.$invite->id,
            );

            $this->audit->record(
                'seller.organization.invite.created',
                actor: $actor,
                auditable: $invite,
                metadata: ['roastery_id' => $roastery->id, 'role' => $role->value],
                request: $request,
            );

            return $invite;
        }, 3);

        return ['invite' => $invite, 'token' => $token];
    }

    public function acceptInvite(User $user, string $token, Request $request): RoasteryMembership
    {
        $tokenHash = hash('sha256', trim($token));

        return DB::transaction(function () use ($user, $tokenHash, $request): RoasteryMembership {
            $invite = RoasteryInvitation::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();
            if (! $invite instanceof RoasteryInvitation) {
                throw (new ModelNotFoundException)->setModel(RoasteryInvitation::class);
            }
            if ($invite->accepted_at !== null || $invite->revoked_at !== null) {
                throw new ApiDomainException(
                    'seller.invite_unavailable',
                    'این دعوت دیگر قابل استفاده نیست.',
                    409,
                );
            }
            if ($invite->expires_at->isPast()) {
                throw new ApiDomainException(
                    'seller.invite_expired',
                    'مهلت این دعوت تمام شده است.',
                    410,
                );
            }

            $mobile = is_string($user->mobile) ? IranMobile::normalize($user->mobile) : '';
            if (
                $mobile === ''
                || ! hash_equals($invite->target_mobile_hash, hash('sha256', $mobile))
                || ($invite->target_user_id !== null && $invite->target_user_id !== $user->id)
            ) {
                throw (new ModelNotFoundException)->setModel(RoasteryInvitation::class);
            }

            if (RoasteryMembership::query()
                ->where('roastery_id', $invite->roastery_id)
                ->where('user_id', $user->id)
                ->exists()) {
                throw new ApiDomainException(
                    'seller.membership_exists',
                    'این حساب از قبل عضو روستری است.',
                    409,
                );
            }

            $membership = RoasteryMembership::query()->create([
                'roastery_id' => $invite->roastery_id,
                'user_id' => $user->id,
                'role' => $invite->role,
                'created_by' => $invite->invited_by,
            ]);
            $invite->forceFill([
                'target_user_id' => $user->id,
                'accepted_at' => now(),
            ])->save();
            $this->access->syncLegacyRole($membership);
            $this->audit->record(
                'seller.organization.invite.accepted',
                actor: $user,
                auditable: $membership,
                metadata: ['roastery_id' => $invite->roastery_id, 'role' => $invite->role->value],
                request: $request,
            );

            return $membership;
        }, 3);
    }

    public function updateMembership(
        User $actor,
        Roastery $roastery,
        string $membershipId,
        RoasteryMembershipRole $role,
        Request $request,
    ): RoasteryMembership {
        $this->access->assertPermission($actor, $roastery, SellerPermission::OrganizationManage);

        return DB::transaction(function () use ($actor, $roastery, $membershipId, $role, $request): RoasteryMembership {
            Roastery::query()->whereKey($roastery->id)->lockForUpdate()->firstOrFail();
            $membership = RoasteryMembership::query()
                ->where('roastery_id', $roastery->id)
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();
            $actorRole = $this->access->effectiveRole($actor, $roastery->id);
            if ($actorRole === RoasteryMembershipRole::Manager && (
                in_array($membership->role, [RoasteryMembershipRole::Owner, RoasteryMembershipRole::Manager], true)
                || in_array($role, [RoasteryMembershipRole::Owner, RoasteryMembershipRole::Manager], true)
            )) {
                throw new AuthorizationException;
            }
            if ($membership->role === RoasteryMembershipRole::Owner && $role !== RoasteryMembershipRole::Owner) {
                $this->assertAnotherActiveOwner($roastery->id, $membership->id);
            }

            $membership->forceFill(['role' => $role])->save();
            $this->access->syncLegacyRole($membership);
            $this->audit->record(
                'seller.organization.member.role_changed',
                actor: $actor,
                auditable: $membership,
                metadata: ['roastery_id' => $roastery->id, 'role' => $role->value],
                request: $request,
            );

            return $membership->refresh();
        }, 3);
    }

    public function removeMembership(
        User $actor,
        Roastery $roastery,
        string $membershipId,
        Request $request,
    ): void {
        $this->access->assertPermission($actor, $roastery, SellerPermission::OrganizationManage);

        DB::transaction(function () use ($actor, $roastery, $membershipId, $request): void {
            Roastery::query()->whereKey($roastery->id)->lockForUpdate()->firstOrFail();
            $membership = RoasteryMembership::query()
                ->where('roastery_id', $roastery->id)
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();
            $actorRole = $this->access->effectiveRole($actor, $roastery->id);
            if ($actorRole === RoasteryMembershipRole::Manager && in_array(
                $membership->role,
                [RoasteryMembershipRole::Owner, RoasteryMembershipRole::Manager],
                true,
            )) {
                throw new AuthorizationException;
            }
            if ($membership->role === RoasteryMembershipRole::Owner) {
                $this->assertAnotherActiveOwner($roastery->id, $membership->id);
            }

            $this->access->removeLegacyRole($membership);
            $this->audit->record(
                'seller.organization.member.removed',
                actor: $actor,
                auditable: $membership,
                metadata: ['roastery_id' => $roastery->id, 'role' => $membership->role->value],
                request: $request,
            );
            $membership->delete();
        }, 3);
    }

    public function revokeInvitation(
        User $actor,
        Roastery $roastery,
        string $inviteId,
        Request $request,
    ): RoasteryInvitation {
        $this->access->assertPermission($actor, $roastery, SellerPermission::OrganizationManage);

        return DB::transaction(function () use ($actor, $roastery, $inviteId, $request): RoasteryInvitation {
            $invite = RoasteryInvitation::query()
                ->where('roastery_id', $roastery->id)
                ->whereKey($inviteId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($invite->accepted_at !== null) {
                throw new ApiDomainException('seller.invite_accepted', 'دعوت پذیرفته‌شده قابل لغو نیست.', 409);
            }
            if ($invite->revoked_at === null) {
                $invite->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
                $this->audit->record(
                    'seller.organization.invite.revoked',
                    actor: $actor,
                    auditable: $invite,
                    metadata: ['roastery_id' => $roastery->id],
                    request: $request,
                );
            }

            return $invite->refresh();
        }, 3);
    }

    /**
     * @param list<array{weekday:int,is_closed:bool,opens_at:?string,closes_at:?string}> $weekly
     * @param list<array{local_date:string,is_closed:bool,opens_at:?string,closes_at:?string,public_reason:?string}> $exceptions
     */
    public function replaceSchedule(
        User $actor,
        Roastery $roastery,
        string $timezone,
        array $weekly,
        array $exceptions,
        Request $request,
    ): void {
        $this->access->assertPermission($actor, $roastery, SellerPermission::AvailabilityWrite);

        DB::transaction(function () use ($actor, $roastery, $timezone, $weekly, $exceptions, $request): void {
            $locked = Roastery::query()->whereKey($roastery->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['timezone' => $timezone])->save();
            RoasteryWeeklyHour::query()->where('roastery_id', $roastery->id)->delete();
            RoasteryScheduleException::query()->where('roastery_id', $roastery->id)->delete();

            foreach ($weekly as $item) {
                RoasteryWeeklyHour::query()->create(['roastery_id' => $roastery->id, ...$item]);
            }
            foreach ($exceptions as $item) {
                $item['public_reason'] = $this->safePublicReason($item['public_reason'] ?? null);
                RoasteryScheduleException::query()->create(['roastery_id' => $roastery->id, ...$item]);
            }

            $this->audit->record(
                'seller.availability.schedule.updated',
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'timezone' => $timezone,
                    'weekly_days' => count($weekly),
                    'exception_dates' => count($exceptions),
                ],
                request: $request,
            );
        }, 3);
    }

    public function startClosure(
        User $actor,
        Roastery $roastery,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $publicReason,
        bool $blocksNewOrders,
        Request $request,
    ): RoasteryClosure {
        $this->access->assertPermission($actor, $roastery, SellerPermission::AvailabilityWrite);
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new ApiDomainException('seller.closure_invalid_range', 'پایان تعطیلی باید بعد از شروع باشد.', 422);
        }
        $publicReason = $this->safePublicReason($publicReason);

        return DB::transaction(function () use (
            $actor,
            $roastery,
            $startsAt,
            $endsAt,
            $publicReason,
            $blocksNewOrders,
            $request,
        ): RoasteryClosure {
            Roastery::query()->whereKey($roastery->id)->lockForUpdate()->firstOrFail();
            $overlap = RoasteryClosure::query()
                ->where('roastery_id', $roastery->id)
                ->whereNull('revoked_at')
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate()
                ->exists();
            if ($overlap) {
                throw new ApiDomainException(
                    'seller.closure_overlap',
                    'بازه تعطیلی با تعطیلی فعال دیگری هم‌پوشانی دارد.',
                    409,
                );
            }

            $closure = RoasteryClosure::query()->create([
                'roastery_id' => $roastery->id,
                'starts_at' => $startsAt->utc(),
                'ends_at' => $endsAt->utc(),
                'public_reason' => $publicReason,
                'blocks_new_orders' => $blocksNewOrders,
                'created_by' => $actor->id,
            ]);
            $this->notifyOrganization(
                $roastery,
                'seller.closure.started',
                [
                    'roastery_name' => (string) $roastery->name,
                    'ends_at' => $endsAt->utc()->toIso8601String(),
                ],
                'seller-closure-started:'.$closure->id,
            );
            $this->audit->record(
                'seller.availability.closure.started',
                actor: $actor,
                auditable: $closure,
                metadata: [
                    'roastery_id' => $roastery->id,
                    'blocks_new_orders' => $blocksNewOrders,
                    'starts_at' => $startsAt->utc()->toIso8601String(),
                    'ends_at' => $endsAt->utc()->toIso8601String(),
                ],
                request: $request,
            );

            return $closure;
        }, 3);
    }

    public function revokeClosure(
        User $actor,
        Roastery $roastery,
        string $closureId,
        Request $request,
    ): RoasteryClosure {
        $this->access->assertPermission($actor, $roastery, SellerPermission::AvailabilityWrite);

        return DB::transaction(function () use ($actor, $roastery, $closureId, $request): RoasteryClosure {
            $closure = RoasteryClosure::query()
                ->where('roastery_id', $roastery->id)
                ->whereKey($closureId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($closure->revoked_at === null) {
                $closure->forceFill(['revoked_at' => now(), 'revoked_by' => $actor->id])->save();
                $this->notifyOrganization(
                    $roastery,
                    'seller.closure.ended',
                    ['roastery_name' => (string) $roastery->name],
                    'seller-closure-ended:'.$closure->id,
                );
                $this->audit->record(
                    'seller.availability.closure.revoked',
                    actor: $actor,
                    auditable: $closure,
                    metadata: ['roastery_id' => $roastery->id],
                    request: $request,
                );
            }

            return $closure->refresh();
        }, 3);
    }

    public function createPromotion(
        User $actor,
        Roastery $roastery,
        string $name,
        ?CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        Request $request,
    ): RoasteryPromotion {
        $this->access->assertPermission($actor, $roastery, SellerPermission::PromotionWrite);
        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new ApiDomainException('seller.promotion_invalid_range', 'بازه Promotion معتبر نیست.', 422);
        }

        $promotion = RoasteryPromotion::query()->create([
            'roastery_id' => $roastery->id,
            'name' => $name,
            'status' => $startsAt === null
                ? RoasteryPromotionStatus::Draft
                : RoasteryPromotionStatus::Scheduled,
            'starts_at' => $startsAt?->utc(),
            'ends_at' => $endsAt?->utc(),
            'created_by' => $actor->id,
        ]);
        $this->audit->record(
            'seller.promotion.created',
            actor: $actor,
            auditable: $promotion,
            metadata: ['roastery_id' => $roastery->id, 'status' => $promotion->status->value],
            request: $request,
        );

        return $promotion;
    }

    public function updatePromotionStatus(
        User $actor,
        Roastery $roastery,
        string $promotionId,
        RoasteryPromotionStatus $status,
        Request $request,
    ): RoasteryPromotion {
        $this->access->assertPermission($actor, $roastery, SellerPermission::PromotionWrite);
        $promotion = RoasteryPromotion::query()
            ->where('roastery_id', $roastery->id)
            ->findOrFail($promotionId);
        if ($promotion->status === RoasteryPromotionStatus::Expired && $status !== RoasteryPromotionStatus::Expired) {
            throw new ApiDomainException('seller.promotion_expired', 'Promotion منقضی دوباره فعال نمی‌شود.', 409);
        }
        if ($promotion->ends_at !== null && $promotion->ends_at->isPast()) {
            $status = RoasteryPromotionStatus::Expired;
        }
        $promotion->forceFill(['status' => $status])->save();
        $this->audit->record(
            'seller.promotion.status_changed',
            actor: $actor,
            auditable: $promotion,
            metadata: ['roastery_id' => $roastery->id, 'status' => $status->value],
            request: $request,
        );

        return $promotion->refresh();
    }

    public function adminRevokeInvitation(User $admin, string $inviteId, Request $request): RoasteryInvitation
    {
        $this->assertAdministrator($admin);
        $invite = RoasteryInvitation::query()->findOrFail($inviteId);
        if ($invite->accepted_at !== null) {
            throw new ApiDomainException('seller.invite_accepted', 'دعوت پذیرفته‌شده قابل لغو نیست.', 409);
        }
        if ($invite->revoked_at === null) {
            $invite->forceFill(['revoked_at' => now(), 'revoked_by' => $admin->id])->save();
            $this->audit->record(
                'admin.seller.invite.revoked',
                actor: $admin,
                auditable: $invite,
                metadata: ['roastery_id' => $invite->roastery_id],
                request: $request,
            );
        }

        return $invite->refresh();
    }

    public function adminSetMembershipLock(
        User $admin,
        string $membershipId,
        bool $locked,
        Request $request,
    ): RoasteryMembership {
        $this->assertAdministrator($admin);

        return DB::transaction(function () use ($admin, $membershipId, $locked, $request): RoasteryMembership {
            $membership = RoasteryMembership::query()->whereKey($membershipId)->lockForUpdate()->firstOrFail();
            Roastery::query()->whereKey($membership->roastery_id)->lockForUpdate()->firstOrFail();
            if ($locked && $membership->role === RoasteryMembershipRole::Owner && ! $membership->is_locked) {
                $this->assertAnotherActiveOwner($membership->roastery_id, $membership->id);
            }
            $membership->forceFill([
                'is_locked' => $locked,
                'locked_at' => $locked ? now() : null,
                'locked_by' => $locked ? $admin->id : null,
            ])->save();
            $this->access->syncLegacyRole($membership);
            $this->audit->record(
                $locked ? 'admin.seller.membership.locked' : 'admin.seller.membership.unlocked',
                actor: $admin,
                auditable: $membership,
                metadata: ['roastery_id' => $membership->roastery_id, 'role' => $membership->role->value],
                request: $request,
            );

            return $membership->refresh();
        }, 3);
    }

    private function assertAnotherActiveOwner(string $roasteryId, string $exceptId): void
    {
        $owners = RoasteryMembership::query()
            ->where('roastery_id', $roasteryId)
            ->where('role', RoasteryMembershipRole::Owner->value)
            ->where('is_locked', false)
            ->whereKeyNot($exceptId)
            ->lockForUpdate()
            ->count();
        if ($owners < 1) {
            throw new ApiDomainException(
                'seller.last_owner_required',
                'آخرین مالک فعال روستری قابل حذف، تنزل نقش یا قفل‌کردن نیست.',
                409,
            );
        }
    }

    private function safePublicReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            return null;
        }
        if (mb_strlen($reason) > 180 || preg_match('/(?:https?:\/\/|www\.|@|\b09\d{9}\b)/iu', $reason)) {
            throw new ApiDomainException(
                'seller.public_reason_unsafe',
                'دلیل عمومی نباید شامل لینک، ایمیل یا شماره تماس باشد.',
                422,
            );
        }

        return $reason;
    }

    /** @param array<string, mixed> $payload */
    private function notifyOrganization(
        Roastery $roastery,
        string $templateKey,
        array $payload,
        string $dedupePrefix,
    ): void {
        $memberships = RoasteryMembership::query()
            ->with('user')
            ->where('roastery_id', $roastery->id)
            ->where('is_locked', false)
            ->whereIn('role', [
                RoasteryMembershipRole::Owner->value,
                RoasteryMembershipRole::Manager->value,
            ])
            ->get();
        foreach ($memberships as $membership) {
            $mobile = $membership->user?->mobile;
            if (! is_string($mobile) || $mobile === '') {
                continue;
            }
            $this->notifier->queueMobile(
                $mobile,
                $templateKey,
                $payload,
                $membership->user,
                $dedupePrefix.':'.$membership->user_id,
            );
        }
    }

    private function assertAdministrator(User $user): void
    {
        if (! $user->hasRole(Role::Administrator)) {
            throw new AuthorizationException;
        }
    }
}
