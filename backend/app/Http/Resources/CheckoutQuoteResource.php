<?php

namespace App\Http\Resources;

use App\Enums\QuotePurpose;
use App\Models\CheckoutQuote;
use App\Models\CheckoutQuoteGroup;
use App\Models\CheckoutQuoteItem;
use Illuminate\Http\Request;

/** @mixin CheckoutQuote */
final class CheckoutQuoteResource extends OkJsonResource
{
    public function toArray(Request $request): array
    {
        $groups = $this->groups
            ->map(function (CheckoutQuoteGroup $group) use ($request): array {
                $items = $group->items
                    ->map(static fn (CheckoutQuoteItem $item): array => [
                        'id' => $item->id,
                        'product' => $item->product_snapshot,
                        'variant' => $item->variant_snapshot,
                        'quantity' => $item->quantity,
                        'line_total' => $item->line_total,
                    ])
                    ->values()
                    ->all();

                $roastery = $items[0]['product']['roastery']
                    ?? (new RoasterySummaryResource($group->roastery))->resolve($request);

                return [
                    'id' => $group->id,
                    'roastery' => $roastery,
                    'items' => $items,
                    'subtotal' => $group->subtotal,
                    'packaging_total' => $group->packaging_total,
                    'grinding_total' => $group->grinding_total,
                    'shipping_cost' => $this->purpose === QuotePurpose::Checkout
                        ? $group->shipping_total
                        : null,
                    'shipping_total' => $this->purpose === QuotePurpose::Checkout
                        ? $group->shipping_total
                        : null,
                    'discount_total' => $group->discount_total,
                    'tax_total' => $group->tax_total,
                    'grand_total' => $group->grand_total,
                    'currency' => $group->currency,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'expires_at' => $this->expires_at->toIso8601String(),
            'roastery_id' => $this->roastery_id,
            'groups' => $groups,
            'subtotal' => $this->subtotal,
            'shipping_total' => $this->shipping_total,
            'discount_total' => $this->discount_total,
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'warnings' => array_values($this->warnings ?? []),
        ];
    }
}
