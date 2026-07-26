<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Resources\GrindingCapabilityResource;
use App\Models\Roastery;
use App\Models\RoasteryGrindingCapability;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RoasteryGrindingCapabilityController
{
    public function __invoke(string $roasterySlug): GrindingCapabilityResource|JsonResponse
    {
        $roastery = Roastery::query()
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->where('slug', $roasterySlug)
            ->firstOrFail();

        $capability = RoasteryGrindingCapability::query()
            ->with([
                'profiles' => static fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('public_name'),
            ])
            ->where('roastery_id', $roastery->id)
            ->where('is_active', true)
            ->first();

        return $capability instanceof RoasteryGrindingCapability
            ? new GrindingCapabilityResource($capability)
            : ApiResponse::success(null);
    }
}
