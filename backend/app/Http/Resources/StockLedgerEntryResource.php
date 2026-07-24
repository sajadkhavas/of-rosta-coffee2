<?php

namespace App\Http\Resources;

use App\Models\StockLedgerEntry;
use Illuminate\Http\Request;

/** @mixin StockLedgerEntry */
final class StockLedgerEntryResource extends OkJsonResource
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
