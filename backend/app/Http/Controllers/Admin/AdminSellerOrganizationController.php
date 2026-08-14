<?php

namespace App\Http\Controllers\Admin;

use App\Models\Roastery;
use App\Models\RoasteryInvitation;
use App\Models\RoasteryMembership;
use App\Models\User;
use App\Services\Seller\SellerOrganizationService;
use App\Support\ApiResponse;
use App\Support\IranMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSellerOrganizationController
{
    public function show(string $roasteryId): JsonResponse
    {
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $members = RoasteryMembership::query()
            ->with('user')
            ->where('roastery_id', $roastery->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (RoasteryMembership $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user?->name,
                'mobile' => $this->maskMobile($membership->user?->mobile),
                'role' => $membership->role->value,
                'is_locked' => $membership->is_locked,
            ])->all();
        $invitations = RoasteryInvitation::query()
            ->where('roastery_id', $roastery->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (RoasteryInvitation $invite): array => [
                'id' => $invite->id,
                'mobile' => $this->maskMobile($invite->target_mobile),
                'role' => $invite->role->value,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'accepted_at' => $invite->accepted_at?->toIso8601String(),
                'revoked_at' => $invite->revoked_at?->toIso8601String(),
            ])->all();

        return ApiResponse::success([
            'roastery' => ['id' => $roastery->id, 'name' => $roastery->name, 'timezone' => $roastery->timezone],
            'members' => $members,
            'invitations' => $invitations,
            'capabilities' => ['read', 'revoke_invitation', 'lock_membership'],
            'impersonation' => false,
        ]);
    }

    public function revokeInvitation(
        Request $request,
        string $inviteId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        /** @var User $admin */
        $admin = $request->user();
        $invite = $organizations->adminRevokeInvitation($admin, $inviteId, $request);

        return ApiResponse::success(['id' => $invite->id, 'revoked' => $invite->revoked_at !== null]);
    }

    public function setMembershipLock(
        Request $request,
        string $membershipId,
        SellerOrganizationService $organizations,
    ): JsonResponse {
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        /** @var User $admin */
        $admin = $request->user();
        $membership = $organizations->adminSetMembershipLock(
            $admin,
            $membershipId,
            (bool) $data['locked'],
            $request,
        );

        return ApiResponse::success([
            'id' => $membership->id,
            'role' => $membership->role->value,
            'is_locked' => $membership->is_locked,
        ]);
    }

    private function maskMobile(?string $mobile): ?string
    {
        if (! is_string($mobile) || $mobile === '') {
            return null;
        }
        $mobile = IranMobile::normalize($mobile);

        return mb_substr($mobile, 0, 4).'****'.mb_substr($mobile, -3);
    }
}
