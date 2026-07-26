<?php

namespace App\Http\Resources;

use App\Enums\FeeMode;
use App\Enums\GrindingAvailability;
use App\Models\RoasteryGrindingCapability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoasteryGrindingCapability */
final class GrindingCapabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = $this->is_active && $this->availability === GrindingAvailability::Available;
        $free = $this->fee_mode === FeeMode::Free || $this->fee_amount === 0;

        return [
            'availability' => $this->availability->value,
            'is_available' => $available,
            'is_active' => $this->is_active,
            'fee_mode' => $this->fee_mode->value,
            'fee_amount' => $free ? 0 : $this->fee_amount,
            'currency' => 'IRR',
            'is_free' => $free,
            'label' => ! $available
                ? 'آسیاب در این روستری ارائه نمی‌شود'
                : ($free ? 'آسیاب روستری رایگان' : 'آسیاب روستری با هزینه'),
            'preparation_minutes' => $this->preparation_minutes,
            'capacity_per_day' => $this->capacity_per_day,
            'supported_weights' => array_values($this->supported_weights),
            'profiles' => GrindingProfileResource::collection($this->whenLoaded('profiles')),
        ];
    }
}
