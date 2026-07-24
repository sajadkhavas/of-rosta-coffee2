<?php

namespace App\Http\Resources;

use App\Models\Roastery;
use Illuminate\Http\Request;

/** @mixin Roastery */
final class RoasteryDetailResource extends RoasterySummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description ?? '',
            'shipping_policy' => $this->shipping_policy,
        ];
    }
}
