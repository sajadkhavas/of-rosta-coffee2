<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Resources\RoasteryDetailResource;
use App\Http\Resources\RoasterySummaryResource;
use App\Models\Roastery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RoasteryController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $roasteries = Roastery::query()
            ->with(['logo', 'cover'])
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->orderByDesc('rating_value')
            ->orderBy('name')
            ->paginate(
                perPage: (int) ($validated['per_page'] ?? 24),
                page: (int) ($validated['page'] ?? 1),
            )
            ->withQueryString();

        return RoasterySummaryResource::collection($roasteries);
    }

    public function show(string $slug): RoasteryDetailResource
    {
        $roastery = Roastery::query()
            ->with(['logo', 'cover'])
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->where('slug', $slug)
            ->firstOrFail();

        return new RoasteryDetailResource($roastery);
    }
}
