<?php

namespace App\Http\Controllers\Cafe;

use App\Http\Resources\CafeResource;
use App\Models\Cafe;
use App\Services\Cafe\CafeDirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CafeDirectoryController
{
    public function index(Request $request, CafeDirectoryService $directory): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'city' => ['nullable', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
        ])->validate();

        $items = $directory->search(
            isset($data['city']) ? (string) $data['city'] : null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            isset($data['radius_km']) ? (float) $data['radius_km'] : 10.0,
        )->map(function (array $entry) use ($request): array {
            return [
                ...(new CafeResource($entry['cafe']))->resolve($request),
                'distance_km' => $entry['distance_km'],
            ];
        })->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $cafe = Cafe::query()->where('slug', $slug)->where('status', 'verified')->whereNotNull('verified_at')->firstOrFail();
        return ApiResponse::success((new CafeResource($cafe))->resolve($request));
    }
}
