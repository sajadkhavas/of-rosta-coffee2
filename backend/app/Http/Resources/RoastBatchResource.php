<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RoastBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'roasted_at' => $this->roasted_at?->toIso8601String(),
            'available_from' => $this->available_from?->toIso8601String(),
        ];
    }
}
