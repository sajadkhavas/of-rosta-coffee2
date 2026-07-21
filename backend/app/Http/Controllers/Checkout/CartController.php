<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\Checkout\CartValidateRequest;
use App\Http\Resources\CheckoutQuoteResource;
use App\Services\Checkout\QuoteService;

final class CartController
{
    public function __invoke(
        CartValidateRequest $request,
        QuoteService $quotes,
    ): CheckoutQuoteResource {
        return new CheckoutQuoteResource(
            $quotes->validateCart($request->validated('items')),
        );
    }
}
