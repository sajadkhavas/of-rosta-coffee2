<?php

namespace App\Http\Controllers\Profile;

use App\Http\Requests\UpsertAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\User;
use App\Services\Identity\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class AddressController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $addresses = $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(
        UpsertAddressRequest $request,
        AddressService $addresses,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $address = $addresses->create($user, $request->validated(), $request);

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpsertAddressRequest $request,
        string $addressId,
        AddressService $addresses,
    ): AddressResource {
        /** @var User $user */
        $user = $request->user();

        return new AddressResource($addresses->update(
            $user,
            $addressId,
            $request->validated(),
            $request,
        ));
    }

    public function destroy(
        Request $request,
        string $addressId,
        AddressService $addresses,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $addresses->delete($user, $addressId, $request);

        return response()->noContent();
    }
}
