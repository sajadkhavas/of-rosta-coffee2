<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\Checkout\CheckoutQuoteRequest;
use App\Http\Resources\CheckoutQuoteResource;
use App\Models\Address;
use App\Models\User;
use App\Services\Checkout\QuoteService;
use App\Services\Seller\SellerAvailabilityGuard;

final class CheckoutQuoteController
{
    public function __invoke(
        CheckoutQuoteRequest $request,
        QuoteService $quotes,
        SellerAvailabilityGuard $availability,
    ): CheckoutQuoteResource {
        /** @var User $user */
        $user = $request->user();

        $address = Address::query()
            ->where('user_id', $user->id)
            ->findOrFail((string) $request->validated('address_id'));
        $items = $request->validated('items');
        $availability->assertAcceptingOrders($items);

        return new CheckoutQuoteResource($quotes->createCheckoutQuote(
            $user,
            $address,
            $items,
            $request->validated('coupon_code'),
        ));
    }
}
