<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Catalog\AdjustStockRequest;
use App\Http\Resources\StockLedgerEntryResource;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Catalog\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SellerInventoryController
{
    public function index(
        Request $request,
        string $roasteryId,
        string $variantId,
        CatalogAccess $access,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $variant = $this->variantForRoastery($roastery, $variantId);

        return StockLedgerEntryResource::collection(
            StockLedgerEntry::query()
                ->where('variant_id', $variant->id)
                ->orderByDesc('created_at')
                ->paginate(100),
        );
    }

    public function adjust(
        AdjustStockRequest $request,
        string $roasteryId,
        string $variantId,
        CatalogAccess $access,
        StockService $stock,
    ): StockLedgerEntryResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $variant = $this->variantForRoastery($roastery, $variantId);
        $data = $request->validated();

        return new StockLedgerEntryResource($stock->adjust(
            $variant,
            $user,
            (int) $data['delta'],
            (string) $data['reason'],
            $data['roast_batch_id'] ?? null,
            $data['idempotency_key'] ?? null,
            $data['metadata'] ?? [],
            $request,
        ));
    }

    private function variantForRoastery(
        Roastery $roastery,
        string $variantId,
    ): ProductVariant {
        return ProductVariant::query()
            ->whereHas(
                'product',
                static fn ($product) => $product->where('roastery_id', $roastery->id),
            )
            ->findOrFail($variantId);
    }
}
