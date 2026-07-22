<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Requests\Content\SetContentStatusRequest;
use App\Http\Requests\Content\UpsertContentEntryRequest;
use App\Http\Resources\ContentEntryDetailResource;
use App\Http\Resources\ContentEntrySummaryResource;
use App\Models\ContentEntry;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Content\ContentPublicationService;
use App\Services\Content\ContentWriteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminContentController
{
    public function index(Request $request, CatalogAccess $access): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return ContentEntrySummaryResource::collection(
            ContentEntry::query()
                ->with('author')
                ->orderByDesc('updated_at')
                ->paginate(100),
        );
    }

    public function store(
        UpsertContentEntryRequest $request,
        CatalogAccess $access,
        ContentWriteService $content,
    ): ContentEntryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return new ContentEntryDetailResource(
            $content->create($request->validated(), $user, $request),
        );
    }

    public function show(
        Request $request,
        string $entryId,
        CatalogAccess $access,
    ): ContentEntryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return new ContentEntryDetailResource(
            ContentEntry::query()
                ->with(['author', 'reviewer', 'relations'])
                ->findOrFail($entryId),
        );
    }

    public function update(
        UpsertContentEntryRequest $request,
        string $entryId,
        CatalogAccess $access,
        ContentWriteService $content,
    ): ContentEntryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $entry = ContentEntry::query()->findOrFail($entryId);

        return new ContentEntryDetailResource(
            $content->update($entry, $request->validated(), $user, $request),
        );
    }

    public function setStatus(
        SetContentStatusRequest $request,
        string $entryId,
        CatalogAccess $access,
        ContentPublicationService $publication,
    ): ContentEntryDetailResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $entry = ContentEntry::query()->findOrFail($entryId);

        return new ContentEntryDetailResource($publication->setStatus(
            $entry,
            ContentStatus::from((string) $request->validated('status')),
            $user,
            $request,
        ));
    }
}
