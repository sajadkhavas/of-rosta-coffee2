<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\Checkout\CheckoutQuoteRequest;
use App\Http\Resources\CheckoutQuoteResource;
use App\Models\Address;
use App\Models\User;
use App\Services\Checkout\QuoteService;

final class CheckoutQuoteController
{
    public function __invoke(
        CheckoutQuoteRequest $request,
        QuoteService $quotes,
    ): CheckoutQuoteResource {
        /** @var User $user */
        $user = $request->user();

        $address = Address::query()
            ->where('user_id', $user->id)
            ->findOrFail((string) $request->validated('address_id'));

        return new CheckoutQuoteResource($quotes->createCheckoutQuote(
            $user,
            $address,
            $request->validated('items'),
            $request->validated('coupon_code'),
        ));
    }
}
