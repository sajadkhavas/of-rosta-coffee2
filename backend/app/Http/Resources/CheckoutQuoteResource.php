<?php

namespace App\Http\Resources;

use App\Enums\QuotePurpose;
use App\Models\CheckoutQuoteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CheckoutQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->items
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
            ?? (new RoasterySummaryResource($this->roastery))->resolve($request);

        return [
            'id' => $this->id,
            'expires_at' => $this->expires_at->toIso8601String(),
            'roastery_id' => $this->roastery_id,
            'groups' => [[
                'roastery' => $roastery,
                'items' => $items,
                'subtotal' => $this->subtotal,
                'shipping_cost' => $this->purpose === QuotePurpose::Checkout ? $this->shipping_total : null,
                'shipping_total' => $this->purpose === QuotePurpose::Checkout ? $this->shipping_total : null,
            ]],
            'subtotal' => $this->subtotal,
            'shipping_total' => $this->shipping_total,
            'discount_total' => $this->discount_total,
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'warnings' => array_values($this->warnings ?? []),
        ];
    }
}
