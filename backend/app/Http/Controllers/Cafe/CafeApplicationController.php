<?php

namespace App\Http\Controllers\Cafe;

use App\Http\Requests\Cafe\ApplyCafeRequest;
use App\Http\Resources\CafeResource;
use App\Models\User;
use App\Services\Cafe\CafeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CafeApplicationController
{
    public function store(ApplyCafeRequest $request, CafeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cafe = $service->apply($user, $request->validated(), $request);

        return ApiResponse::success((new CafeResource($cafe))->resolve($request), 201);
    }
}
