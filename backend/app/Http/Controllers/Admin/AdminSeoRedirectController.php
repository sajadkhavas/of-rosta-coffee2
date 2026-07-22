<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Content\UpsertSeoRedirectRequest;
use App\Http\Resources\SeoRedirectResource;
use App\Models\SeoRedirect;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Content\SeoRedirectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AdminSeoRedirectController
{
    public function index(Request $request, CatalogAccess $access): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return SeoRedirectResource::collection(
            SeoRedirect::query()->orderByDesc('updated_at')->paginate(100),
        );
    }

    public function store(
        UpsertSeoRedirectRequest $request,
        CatalogAccess $access,
        SeoRedirectService $redirects,
    ): SeoRedirectResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return new SeoRedirectResource(
            $redirects->create($request->validated(), $user, $request),
        );
    }

    public function update(
        UpsertSeoRedirectRequest $request,
        string $redirectId,
        CatalogAccess $access,
        SeoRedirectService $redirects,
    ): SeoRedirectResource {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $redirect = SeoRedirect::query()->findOrFail($redirectId);

        return new SeoRedirectResource(
            $redirects->update($redirect, $request->validated(), $user, $request),
        );
    }
}
