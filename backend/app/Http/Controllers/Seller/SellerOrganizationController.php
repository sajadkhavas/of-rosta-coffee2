<?php

namespace App\Http\Controllers\Seller;

use App\Enums\RoasteryMembershipRole;
use App\Enums\RoasteryPromotionStatus;
use App\Models\Roastery;
use App\Models\RoasteryClosure;
use App\Models\RoasteryInvitation;
use App\Models\RoasteryMembership;
use App\Models\RoasteryPromotion;
use App\Models\RoasteryScheduleException;
use App\Models\RoasteryWeeklyHour;
use App\Models\User;
use App\Services\Seller\RoasteryAvailability;
use App\Services\Seller\SellerAccess;
use App\Services\Seller\SellerOrganizationService;
use App\Support\ApiResponse;
use App\Support\IranMobile;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SellerOrganizationController
{
    public function show(
        Request $request,
        string $roasteryId,
        SellerAccess $access,
        RoasteryAvailability $availability,
    ): JsonResponse {
        $roastery = $this->roastery($roasteryId);
        /** @var User $user */
        $user = $request->user();
        $role = $access->effectiveRole($user, $roastery->id);

        return ApiResponse::success([
            'roastery_id' => $roastery->id,
            'timezone' => $roastery->timezone,
            'role' => $role?->value,
            'permissions' => $role === null
                ? []
                : array_map(static fn ($permission): string => $permission->value, $access->permissionsForRole($role)),
            'availability' => $availability->snapshot($roastery),
            'members' => $this->members($roastery),
            'invitations' => $this->invitations($roastery),
            'weekly_hours' => $this->weeklyHours($roastery),
            'exceptions' => $this->exceptions($roastery),
            'closures' => $this->closures($roastery),
            'promotions' => $this->promotions($roastery),
        ]);
    }

    public function listMembers(string $roasteryId): JsonResponse
    {
        return ApiResponse::success(['items' => $this->members($this->roastery($roasteryId))]);
    }

    public function listInvitations(string $roasteryId): JsonResponse
    {
        return ApiResponse::success(['items' => $this->invitations($this->roastery($roasteryId))]);
    }

    public function createInvitation(
        Request $request,
        string $roasteryId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $data = $request->validate([
            'mobile' => [
                'required',
                'string',
                'max:32',
                static fn (string $attribute, mixed $value, \Closure $fail) => IranMobile::isValid((string) $value)
                    ?: $fail('شماره موبایل ایران معتبر نیست.'),
            ],
            'role' => ['required', Rule::enum(RoasteryMembershipRole::class)],
        ]);
        /** @var User $user */
        $user = $request->user();
        $result = $organizations->invite(
            $user,
            $this->roastery($roasteryId),
            (string) $data['mobile'],
            RoasteryMembershipRole::from((string) $data['role']),
            $request,
        );

        return ApiResponse::success([
            'invitation' => $this->invitation($result['invite']),
            'token' => $result['token'],
        ], 201);
    }

    public function acceptInvitation(
        Request $request,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']]);
        /** @var User $user */
        $user = $request->user();
        $membership = $organizations->acceptInvite($user, (string) $data['token'], $request);

        return ApiResponse::success(['membership' => $this->member($membership->load('user'))]);
    }

    public function updateMember(
        Request $request,
        string $roasteryId,
        string $membershipId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $data = $request->validate(['role' => ['required', Rule::enum(RoasteryMembershipRole::class)]]);
        /** @var User $user */
        $user = $request->user();
        $membership = $organizations->updateMembership(
            $user,
            $this->roastery($roasteryId),
            $membershipId,
            RoasteryMembershipRole::from((string) $data['role']),
            $request,
        );

        return ApiResponse::success(['membership' => $this->member($membership->load('user'))]);
    }

    public function removeMember(
        Request $request,
        string $roasteryId,
        string $membershipId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $organizations->removeMembership($user, $this->roastery($roasteryId), $membershipId, $request);

        return ApiResponse::success(['removed' => true]);
    }

    public function revokeInvitation(
        Request $request,
        string $roasteryId,
        string $inviteId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $invite = $organizations->revokeInvitation(
            $user,
            $this->roastery($roasteryId),
            $inviteId,
            $request,
        );

        return ApiResponse::success(['invitation' => $this->invitation($invite)]);
    }

    public function showSchedule(
        string $roasteryId,
        RoasteryAvailability $availability,
    ): JsonResponse {
        $roastery = $this->roastery($roasteryId);

        return ApiResponse::success([
            'timezone' => $roastery->timezone,
            'weekly_hours' => $this->weeklyHours($roastery),
            'exceptions' => $this->exceptions($roastery),
            'availability' => $availability->snapshot($roastery),
        ]);
    }

    public function updateSchedule(
        Request $request,
        string $roasteryId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'weekly_hours' => ['required', 'array', 'max:7'],
            'weekly_hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'weekly_hours.*.is_closed' => ['required', 'boolean'],
            'weekly_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'weekly_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'exceptions' => ['present', 'array', 'max:180'],
            'exceptions.*.local_date' => ['required', 'date_format:Y-m-d'],
            'exceptions.*.is_closed' => ['required', 'boolean'],
            'exceptions.*.opens_at' => ['nullable', 'date_format:H:i'],
            'exceptions.*.closes_at' => ['nullable', 'date_format:H:i'],
            'exceptions.*.public_reason' => ['nullable', 'string', 'max:180'],
        ]);
        $weekly = array_values($data['weekly_hours']);
        $exceptions = array_values($data['exceptions']);
        $this->assertScheduleRows($weekly, $exceptions);
        /** @var User $user */
        $user = $request->user();
        $organizations->replaceSchedule(
            $user,
            $this->roastery($roasteryId),
            (string) $data['timezone'],
            $weekly,
            $exceptions,
            $request,
        );

        return $this->showSchedule($roasteryId, app(RoasteryAvailability::class));
    }

    public function listClosures(string $roasteryId): JsonResponse
    {
        return ApiResponse::success(['items' => $this->closures($this->roastery($roasteryId))]);
    }

    public function createClosure(
        Request $request,
        string $roasteryId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'public_reason' => ['nullable', 'string', 'max:180'],
            'blocks_new_orders' => ['required', 'boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $closure = $organizations->startClosure(
            $user,
            $this->roastery($roasteryId),
            CarbonImmutable::parse((string) $data['starts_at']),
            CarbonImmutable::parse((string) $data['ends_at']),
            $data['public_reason'] ?? null,
            (bool) $data['blocks_new_orders'],
            $request,
        );

        return ApiResponse::success(['closure' => $this->closure($closure)], 201);
    }

    public function revokeClosure(
        Request $request,
        string $roasteryId,
        string $closureId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $closure = $organizations->revokeClosure(
            $user,
            $this->roastery($roasteryId),
            $closureId,
            $request,
        );

        return ApiResponse::success(['closure' => $this->closure($closure)]);
    }

    public function listPromotions(string $roasteryId): JsonResponse
    {
        return ApiResponse::success(['items' => $this->promotions($this->roastery($roasteryId))]);
    }

    public function createPromotion(
        Request $request,
        string $roasteryId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $this->rejectPricingFields($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $promotion = $organizations->createPromotion(
            $user,
            $this->roastery($roasteryId),
            trim((string) $data['name']),
            isset($data['starts_at']) ? CarbonImmutable::parse((string) $data['starts_at']) : null,
            isset($data['ends_at']) ? CarbonImmutable::parse((string) $data['ends_at']) : null,
            $request,
        );

        return ApiResponse::success(['promotion' => $this->promotion($promotion)], 201);
    }

    public function updatePromotion(
        Request $request,
        string $roasteryId,
        string $promotionId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $this->rejectPricingFields($request);
        $data = $request->validate(['status' => ['required', Rule::enum(RoasteryPromotionStatus::class)]]);
        /** @var User $user */
        $user = $request->user();
        $promotion = $organizations->updatePromotionStatus(
            $user,
            $this->roastery($roasteryId),
            $promotionId,
            RoasteryPromotionStatus::from((string) $data['status']),
            $request,
        );

        return ApiResponse::success(['promotion' => $this->promotion($promotion)]);
    }

    /** @return list<array<string, mixed>> */
    private function members(Roastery $roastery): array
    {
        return RoasteryMembership::query()
            ->with('user')
            ->where('roastery_id', $roastery->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (RoasteryMembership $membership): array => $this->member($membership))
            ->all();
    }

    /** @return array<string, mixed> */
    private function member(RoasteryMembership $membership): array
    {
        return [
            'id' => $membership->id,
            'user_id' => $membership->user_id,
            'name' => $membership->user?->name,
            'mobile' => $this->maskMobile($membership->user?->mobile),
            'role' => $membership->role->value,
            'is_locked' => $membership->is_locked,
            'created_at' => $membership->created_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function invitations(Roastery $roastery): array
    {
        return RoasteryInvitation::query()
            ->where('roastery_id', $roastery->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (RoasteryInvitation $invite): array => $this->invitation($invite))
            ->all();
    }

    /** @return array<string, mixed> */
    private function invitation(RoasteryInvitation $invite): array
    {
        $status = match (true) {
            $invite->accepted_at !== null => 'accepted',
            $invite->revoked_at !== null => 'revoked',
            $invite->expires_at->isPast() => 'expired',
            default => 'pending',
        };

        return [
            'id' => $invite->id,
            'mobile' => $this->maskMobile($invite->target_mobile),
            'role' => $invite->role->value,
            'status' => $status,
            'expires_at' => $invite->expires_at->toIso8601String(),
            'created_at' => $invite->created_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function weeklyHours(Roastery $roastery): array
    {
        return RoasteryWeeklyHour::query()
            ->where('roastery_id', $roastery->id)
            ->orderBy('weekday')
            ->get(['weekday', 'is_closed', 'opens_at', 'closes_at'])
            ->map(static fn (RoasteryWeeklyHour $item): array => [
                'weekday' => $item->weekday,
                'is_closed' => $item->is_closed,
                'opens_at' => $item->opens_at,
                'closes_at' => $item->closes_at,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function exceptions(Roastery $roastery): array
    {
        return RoasteryScheduleException::query()
            ->where('roastery_id', $roastery->id)
            ->where('local_date', '>=', now()->subYear()->toDateString())
            ->orderBy('local_date')
            ->limit(365)
            ->get()
            ->map(static fn (RoasteryScheduleException $item): array => [
                'local_date' => $item->local_date->toDateString(),
                'is_closed' => $item->is_closed,
                'opens_at' => $item->opens_at,
                'closes_at' => $item->closes_at,
                'public_reason' => $item->public_reason,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function closures(Roastery $roastery): array
    {
        return RoasteryClosure::query()
            ->where('roastery_id', $roastery->id)
            ->where('ends_at', '>=', now()->subMonth())
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get()
            ->map(fn (RoasteryClosure $closure): array => $this->closure($closure))
            ->all();
    }

    /** @return array<string, mixed> */
    private function closure(RoasteryClosure $closure): array
    {
        return [
            'id' => $closure->id,
            'starts_at' => $closure->starts_at->utc()->toIso8601String(),
            'ends_at' => $closure->ends_at->utc()->toIso8601String(),
            'public_reason' => $closure->public_reason,
            'blocks_new_orders' => $closure->blocks_new_orders,
            'is_active' => $closure->revoked_at === null
                && $closure->starts_at->isPast()
                && $closure->ends_at->isFuture(),
            'revoked_at' => $closure->revoked_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function promotions(Roastery $roastery): array
    {
        RoasteryPromotion::query()
            ->where('roastery_id', $roastery->id)
            ->whereNot('status', RoasteryPromotionStatus::Expired->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => RoasteryPromotionStatus::Expired->value, 'updated_at' => now()]);

        return RoasteryPromotion::query()
            ->where('roastery_id', $roastery->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (RoasteryPromotion $promotion): array => $this->promotion($promotion))
            ->all();
    }

    /** @return array<string, mixed> */
    private function promotion(RoasteryPromotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'status' => $promotion->status->value,
            'starts_at' => $promotion->starts_at?->utc()->toIso8601String(),
            'ends_at' => $promotion->ends_at?->utc()->toIso8601String(),
            'pricing_applied' => false,
        ];
    }

    private function roastery(string $roasteryId): Roastery
    {
        return Roastery::query()->findOrFail($roasteryId);
    }

    private function maskMobile(?string $mobile): ?string
    {
        if (! is_string($mobile) || $mobile === '') {
            return null;
        }
        $mobile = IranMobile::normalize($mobile);
        if (mb_strlen($mobile) < 7) {
            return '***';
        }

        return mb_substr($mobile, 0, 4).'****'.mb_substr($mobile, -3);
    }

    /**
     * @param  list<array<string, mixed>>  $weekly
     * @param  list<array<string, mixed>>  $exceptions
     */
    private function assertScheduleRows(array $weekly, array $exceptions): void
    {
        $weekdays = array_column($weekly, 'weekday');
        $dates = array_column($exceptions, 'local_date');
        $errors = [];
        if (count($weekdays) !== count(array_unique($weekdays))) {
            $errors['weekly_hours'] = ['هر روز هفته فقط یک بار قابل ثبت است.'];
        }
        if (count($dates) !== count(array_unique($dates))) {
            $errors['exceptions'] = ['هر تاریخ استثنا فقط یک بار قابل ثبت است.'];
        }
        foreach (array_merge($weekly, $exceptions) as $index => $row) {
            if (($row['is_closed'] ?? false) === false) {
                if (empty($row['opens_at']) || empty($row['closes_at'])) {
                    $errors['schedule.'.$index] = ['برای روز باز، ساعت شروع و پایان الزامی است.'];
                } elseif ($row['opens_at'] === $row['closes_at']) {
                    $errors['schedule.'.$index] = ['ساعت شروع و پایان نمی‌تواند یکسان باشد.'];
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function rejectPricingFields(Request $request): void
    {
        foreach (['discount', 'discount_percent', 'price', 'amount', 'coupon', 'rate'] as $field) {
            if ($request->exists($field)) {
                throw ValidationException::withMessages([
                    $field => ['اعمال قیمت و تخفیف در PS5.2 مجاز نیست.'],
                ]);
            }
        }
    }
}
