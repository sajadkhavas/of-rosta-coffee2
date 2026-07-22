<?php

namespace App\Http\Controllers\Content;

use App\Http\Resources\SeoRedirectResource;
use App\Models\ContentEntry;
use App\Services\Content\SeoRedirectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SeoController
{
    public function indexable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $entries = ContentEntry::query()
            ->indexable()
            ->select(['id', 'type', 'canonical_path', 'updated_at'])
            ->orderBy('id')
            ->cursorPaginate(
                perPage: (int) ($validated['limit'] ?? 500),
                cursor: $validated['cursor'] ?? null,
            );

        return ApiResponse::success([
            'items' => collect($entries->items())->map(static fn (ContentEntry $entry): array => [
                'path' => $entry->canonical_path,
                'type' => $entry->type->value,
                'last_modified_at' => $entry->updated_at?->toIso8601String(),
            ])->values()->all(),
            'next_cursor' => $entries->nextCursor()?->encode(),
        ]);
    }

    public function redirect(Request $request, SeoRedirectService $redirects): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);
        $redirect = $redirects->resolve((string) $validated['path']);

        return ApiResponse::success([
            'redirect' => $redirect ? (new SeoRedirectResource($redirect))->resolve($request) : null,
        ]);
    }
}
