<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Content\UpsertContentAuthorRequest;
use App\Http\Resources\ContentAuthorResource;
use App\Models\ContentAuthor;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminContentAuthorController
{
    public function index(Request $request, CatalogAccess $access): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return ContentAuthorResource::collection(
            ContentAuthor::query()->orderBy('name')->paginate(100),
        );
    }

    public function store(
        UpsertContentAuthorRequest $request,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): ContentAuthorResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $author = ContentAuthor::query()->create([
            ...$request->validated(),
            'credentials' => $request->validated('credentials') ?? [],
            'is_active' => $request->validated('is_active') ?? true,
        ]);
        $audit->record('content.author.created', actor: $user, auditable: $author, request: $request);

        return new ContentAuthorResource($author);
    }

    public function update(
        UpsertContentAuthorRequest $request,
        string $authorId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): ContentAuthorResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        $author = ContentAuthor::query()->findOrFail($authorId);
        $author->fill($request->validated())->save();
        $audit->record(
            'content.author.updated',
            actor: $user,
            auditable: $author,
            metadata: ['fields' => array_keys($request->validated())],
            request: $request,
        );

        return new ContentAuthorResource($author->refresh());
    }
}
