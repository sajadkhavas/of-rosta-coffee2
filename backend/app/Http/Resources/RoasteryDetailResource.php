<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

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
