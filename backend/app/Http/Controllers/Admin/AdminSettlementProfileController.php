<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Settlement\ReviewRoasterySettlementProfileRequest;
use App\Models\RoasterySettlementProfile;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminSettlementProfileController
{
    public function index(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $status = trim((string) $request->query('status', 'pending_review'));
        $query = RoasterySettlementProfile::query()->with('roastery')->latest('submitted_at');
        if (in_array($status, ['pending_review', 'verified', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $profiles = $query->limit(200)->get();

        return ApiResponse::success([
            'items' => $profiles->map(fn (RoasterySettlementProfile $profile): array => $this->adminPayload($profile))->all(),
        ]);
    }

    public function review(
        ReviewRoasterySettlementProfileRequest $request,
        string $profileId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $validated = $request->validated();

        $profile = DB::transaction(function () use ($user, $profileId, $validated, $request, $audit): RoasterySettlementProfile {
            $profile = RoasterySettlementProfile::query()->lockForUpdate()->findOrFail($profileId);
            $profile->forceFill([
                'status' => $validated['decision'],
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
                'review_note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
            ])->save();

            $audit->record(
                'admin.settlement_profile.reviewed',
                actor: $user,
                auditable: $profile,
                metadata: [
                    'roastery_id' => $profile->roastery_id,
                    'decision' => $profile->status,
                    'iban_last4' => $profile->iban_last4,
                ],
                request: $request,
            );

            return $profile->refresh()->load('roastery');
        }, 3);

        return ApiResponse::success($this->adminPayload($profile));
    }

    /** @return array<string, mixed> */
    private function adminPayload(RoasterySettlementProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'roastery' => [
                'id' => $profile->roastery_id,
                'name' => $profile->roastery->name,
            ],
            'entity_type' => $profile->entity_type,
            'legal_name' => $profile->legal_name,
            'account_holder_name' => $profile->account_holder_name,
            'iban' => $profile->iban,
            'iban_masked' => $profile->maskedIban(),
            'status' => $profile->status,
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
            'reviewed_at' => $profile->reviewed_at?->toIso8601String(),
            'review_note' => $profile->review_note,
        ];
    }
}
