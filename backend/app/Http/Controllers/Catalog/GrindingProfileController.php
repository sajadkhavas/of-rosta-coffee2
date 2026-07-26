<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Resources\GrindingProfileResource;
use App\Models\GrindingProfile;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class GrindingProfileController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $profiles = GrindingProfile::query()
            ->where('is_active', true)
            ->orderBy('public_name')
            ->get();

        return GrindingProfileResource::collection($profiles);
    }
}
