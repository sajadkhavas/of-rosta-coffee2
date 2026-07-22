<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'roast_batch_id' => $this->roast_batch_id,
            'delta' => $this->delta,
            'balance_after' => $this->balance_after,
            'reason' => $this->reason->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
