<?php

namespace App\Http\Controllers\Cafe;

use App\Http\Requests\Cafe\UpdateCafeRequest;
use App\Http\Resources\CafeResource;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\User;
use App\Services\Cafe\CafeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CafeAccountController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $memberships = CafeMembership::query()->with('cafe')->where('user_id', $user->id)->where('is_active', true)->get();

        return ApiResponse::success(['items' => $memberships->map(fn (CafeMembership $membership): array => [
            ...(new CafeResource($membership->cafe))->resolve($request),
            'membership_role' => $membership->role,
        ])->all()]);
    }

    public function update(UpdateCafeRequest $request, string $cafeId, CafeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cafe = Cafe::query()->findOrFail($cafeId);
        $updated = $service->updateForMember($cafe, $user, $request->validated(), $request);

        return ApiResponse::success((new CafeResource($updated))->resolve($request));
    }
}
