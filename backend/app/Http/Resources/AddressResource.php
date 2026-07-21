<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'recipient_name' => $this->resource->recipient_name,
            'recipient_mobile' => $this->resource->recipient_mobile,
            'province' => $this->resource->province,
            'city' => $this->resource->city,
            'address_line' => $this->resource->address_line,
            'postal_code' => $this->resource->postal_code,
            'is_default' => (bool) $this->resource->is_default,
        ];
    }
}
