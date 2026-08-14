<?php

namespace App\Http\Resources;

use App\Enums\QuotePurpose;
use App\Models\CheckoutQuote;
use App\Models\CheckoutQuoteGroup;
use App\Models\CheckoutQuoteItem;
use App\Models\CheckoutQuoteItemService;
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
                        'discount_amount' => $item->discount_amount,
                        'tax_amount' => $item->tax_amount,
                        'commission_amount' => $item->commission_amount,
                        'payable_amount' => $item->net_amount,
                        'services' => $item->services->map(static fn (CheckoutQuoteItemService $service): array => [
                            'id' => $service->id,
                            'type' => $service->service_type,
                            'provider_type' => $service->provider_type,
                            'grinding_profile' => $service->grinding_profile_id === null
                                ? null
                                : [
                                    'id' => $service->service_snapshot['profile']['id'],
                                    'code' => $service->service_snapshot['profile']['code'],
                                    'version' => $service->service_snapshot['profile']['version'],
                                    'name' => $service->service_snapshot['profile']['name'],
                                    'brew_method' => $service->service_snapshot['profile']['brew_method'],
                                ],
                            'service_fee' => $service->service_fee,
                            'packaging_fee' => $service->packaging_fee,
                            'tax_amount' => $service->tax_amount,
                            'commission_amount' => $service->commission_amount,
                            'total_amount' => $service->total_amount,
                            'currency' => $service->currency,
                            'is_free' => $service->total_amount === 0,
                            'label' => $service->service_snapshot['label'] ?? null,
                        ])->values()->all(),
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
                    'commission_total' => $group->commission_total,
                    'payable_total' => $group->payable_total,
                    'grand_total' => $group->grand_total,
                    'currency' => $group->currency,
                    'financial_policy' => $this->policySummary($group->financial_snapshot),
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
            'packaging_total' => $this->groups->sum('packaging_total'),
            'grinding_total' => $this->groups->sum('grinding_total'),
            'shipping_total' => $this->shipping_total,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'commission_total' => $this->commission_total,
            'grand_total' => $this->grand_total,
            'currency' => $this->currency,
            'warnings' => array_values($this->warnings ?? []),
        ];
    }

    /** @param array<string, mixed>|null $snapshot */
    private function policySummary(?array $snapshot): array
    {
        return [
            'status' => $snapshot['status'] ?? 'legacy_unknown',
            'calculation_version' => $snapshot['calculation_version'] ?? ($snapshot['version'] ?? null),
            'tax_policy_version' => $snapshot['tax_policy']['version'] ?? null,
            'tax_policy_checksum' => $snapshot['tax_policy']['checksum'] ?? null,
            'commission_policy_version' => $snapshot['commission_policy']['version'] ?? null,
            'commission_policy_checksum' => $snapshot['commission_policy']['checksum'] ?? null,
        ];
    }
}
