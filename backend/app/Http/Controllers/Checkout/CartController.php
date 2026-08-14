<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\Checkout\CartValidateRequest;
use App\Http\Resources\CheckoutQuoteResource;
use App\Services\Checkout\QuoteService;
use App\Services\Seller\SellerAvailabilityGuard;

final class CartController
{
    public function __invoke(
        CartValidateRequest $request,
        QuoteService $quotes,
        SellerAvailabilityGuard $availability,
    ): CheckoutQuoteResource {
        $items = $request->validated('items');
        $availability->assertAcceptingOrders($items);

        return new CheckoutQuoteResource($quotes->validateCart($items));
    }
}
