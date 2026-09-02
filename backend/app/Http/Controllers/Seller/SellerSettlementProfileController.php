<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Settlement\UpdateRoasterySettlementProfileRequest;
use App\Models\Roastery;
use App\Models\RoasterySettlementProfile;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SellerSettlementProfileController
{
    public function show(Request $request, string $roasteryId): JsonResponse
    {
        $profile = RoasterySettlementProfile::query()
            ->where('roastery_id', $roasteryId)
            ->first();

        return ApiResponse::success([
            'profile' => $profile instanceof RoasterySettlementProfile
                ? $this->sellerPayload($profile)
                : null,
        ]);
    }

    public function update(
        UpdateRoasterySettlementProfileRequest $request,
        string $roasteryId,
        AuditRecorder $audit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $validated = $request->validated();
        $iban = (string) $validated['iban'];

        $profile = DB::transaction(function () use (
            $user,
            $roastery,
            $validated,
            $iban,
            $request,
            $audit,
        ): RoasterySettlementProfile {
            $profile = RoasterySettlementProfile::query()
                ->where('roastery_id', $roastery->id)
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof RoasterySettlementProfile) {
                $profile = new RoasterySettlementProfile(['roastery_id' => $roastery->id]);
            }

            $profile->fill([
                'entity_type' => $validated['entity_type'],
                'legal_name' => trim((string) $validated['legal_name']),
                'account_holder_name' => trim((string) $validated['account_holder_name']),
                'iban' => $iban,
                'iban_last4' => substr($iban, -4),
                'status' => 'pending_review',
                'submitted_by_id' => $user->id,
                'submitted_at' => now(),
                'reviewed_by_id' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);
            $profile->save();

            $audit->record(
                'seller.settlement_profile.submitted',
                actor: $user,
                auditable: $profile,
                metadata: [
                    'roastery_id' => $roastery->id,
                    'entity_type' => $profile->entity_type,
                    'iban_last4' => $profile->iban_last4,
                ],
                request: $request,
            );

            return $profile->refresh();
        }, 3);

        return ApiResponse::success(['profile' => $this->sellerPayload($profile)]);
    }

    /** @return array<string, mixed> */
    private function sellerPayload(RoasterySettlementProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'roastery_id' => $profile->roastery_id,
            'entity_type' => $profile->entity_type,
            'legal_name' => $profile->legal_name,
            'account_holder_name' => $profile->account_holder_name,
            'iban_masked' => $profile->maskedIban(),
            'status' => $profile->status,
            'submitted_at' => $profile->submitted_at?->toIso8601String(),
            'reviewed_at' => $profile->reviewed_at?->toIso8601String(),
            'review_note' => $profile->review_note,
        ];
    }
}
