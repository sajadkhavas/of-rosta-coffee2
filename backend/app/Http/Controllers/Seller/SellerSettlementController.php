<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SettlementAllocationStatus;
use App\Models\Roastery;
use App\Models\SettlementAllocation;
use App\Models\SettlementBatch;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SellerSettlementController
{
    public function index(Request $request, string $roasteryId, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $batches = SettlementBatch::query()
            ->where('roastery_id', $roastery->id)
            ->latest('created_at')
            ->limit(100)
            ->get();
        $summary = SettlementAllocation::query()
            ->where('owner_type', 'roastery')
            ->where('owner_id', $roastery->id)
            ->selectRaw('status, SUM(net_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return ApiResponse::success([
            'summary' => [
                'held' => (int) ($summary[SettlementAllocationStatus::Held->value] ?? 0),
                'eligible' => (int) ($summary[SettlementAllocationStatus::Eligible->value] ?? 0),
                'scheduled' => (int) ($summary[SettlementAllocationStatus::Scheduled->value] ?? 0),
                'paid' => (int) ($summary[SettlementAllocationStatus::Paid->value] ?? 0),
                'currency' => 'IRR',
            ],
            'batches' => $batches->map(static fn (SettlementBatch $batch): array => [
                'id' => $batch->id,
                'status' => $batch->status->value,
                'net_total' => $batch->net_total,
                'allocation_count' => $batch->allocation_count,
                'currency' => $batch->currency,
                'payout_reference' => $batch->payout_reference,
                'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
                'paid_at' => $batch->paid_at?->toIso8601String(),
                'failed_at' => $batch->failed_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
