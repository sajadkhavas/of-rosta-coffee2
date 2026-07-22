<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subOrder = $this->subOrder;
        $items = $subOrder->items
            ->map(static function (OrderItem $item): array {
                $product = $item->product_snapshot;
                $variant = $item->variant_snapshot;

                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $product['id'],
                        'name' => $product['name'],
                        'slug' => $product['slug'],
                        'primary_image' => $product['primary_image'] ?? null,
                    ],
                    'variant' => [
                        'id' => $variant['id'],
                        'sku' => $variant['sku'],
                        'weight_grams' => $variant['weight_grams'],
                        'price' => $item->unit_price,
                        'currency' => $variant['currency'] ?? 'IRR',
                    ],
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'sub_orders' => [[
                'id' => $subOrder->id,
                'status' => $subOrder->status->value,
                'roastery' => [
                    'id' => $subOrder->roastery->id,
                    'name' => $subOrder->roastery->name,
                    'slug' => $subOrder->roastery->slug,
                ],
                'items' => $items,
                'subtotal' => $subOrder->subtotal,
                'shipping_total' => $subOrder->shipping_total,
                'shipment' => null,
            ]],
            'address' => $this->address_snapshot,
            'subtotal' => $this->subtotal,
            'shipping_total' => $this->shipping_total,
            'discount_total' => $this->discount_total,
        ];
    }
}
