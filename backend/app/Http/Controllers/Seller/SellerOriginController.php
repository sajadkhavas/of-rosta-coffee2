<?php

namespace App\Http\Controllers\Seller;

use App\Http\Resources\OriginResource;
use App\Models\Origin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SellerOriginController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return OriginResource::collection(
            Origin::query()
                ->orderBy('name')
                ->paginate(100),
        );
    }
}
