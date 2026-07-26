<?php

namespace App\Services\Catalog;

use App\Enums\FeeMode;
use App\Enums\GrindingAvailability;
use App\Models\GrindingProfile;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RoasteryGrindingPolicy
{
    /**
     * @param  array{
     *     availability: string,
     *     fee_mode: string,
     *     fee_amount: int,
     *     preparation_minutes: int,
     *     capacity_per_day: int|null,
     *     supported_weights: list<int>,
     *     grinding_profile_ids: list<string>,
     *     is_active: bool
     * }  $data
     */
    public function upsert(Roastery $roastery, array $data): RoasteryGrindingCapability
    {
        $availability = GrindingAvailability::from($data['availability']);
        $feeMode = FeeMode::from($data['fee_mode']);
        $feeAmount = $feeMode === FeeMode::Free ? 0 : $data['fee_amount'];
        $weights = array_values(array_unique($data['supported_weights']));
        sort($weights);
        $profileIds = array_values(array_unique($data['grinding_profile_ids']));

        if ($feeMode === FeeMode::Fixed && $feeAmount < 1) {
            throw ValidationException::withMessages([
                'fee_amount' => ['برای هزینه ثابت آسیاب، مبلغ باید بیشتر از صفر باشد.'],
            ]);
        }

        if ($availability === GrindingAvailability::Available && $data['is_active']) {
            if ($weights === []) {
                throw ValidationException::withMessages([
                    'supported_weights' => ['حداقل یک وزن دانه کامل باید پشتیبانی شود.'],
                ]);
            }

            if ($profileIds === []) {
                throw ValidationException::withMessages([
                    'grinding_profile_ids' => ['حداقل یک پروفایل آسیاب فعال باید انتخاب شود.'],
                ]);
            }
        }

        $activeProfileCount = GrindingProfile::query()
            ->where('is_active', true)
            ->whereIn('id', $profileIds)
            ->count();
        if ($activeProfileCount !== count($profileIds)) {
            throw ValidationException::withMessages([
                'grinding_profile_ids' => ['یکی از پروفایل‌های آسیاب معتبر یا فعال نیست.'],
            ]);
        }

        return DB::transaction(function () use (
            $roastery,
            $availability,
            $feeMode,
            $feeAmount,
            $weights,
            $profileIds,
            $data,
        ): RoasteryGrindingCapability {
            $capability = RoasteryGrindingCapability::query()->updateOrCreate(
                ['roastery_id' => $roastery->id],
                [
                    'availability' => $availability->value,
                    'fee_mode' => $feeMode->value,
                    'fee_amount' => $feeAmount,
                    'preparation_minutes' => $data['preparation_minutes'],
                    'capacity_per_day' => $data['capacity_per_day'],
                    'supported_weights' => $weights,
                    'is_active' => $data['is_active'],
                ],
            );

            $capability->profiles()->sync($profileIds);

            return $capability->refresh()->load('profiles');
        });
    }
}
