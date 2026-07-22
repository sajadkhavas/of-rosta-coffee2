<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Catalog\StoreRoastBatchRequest;
use App\Http\Resources\RoastBatchResource;
use App\Models\Product;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\Catalog\CatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SellerRoastBatchController
{
    public function index(
        Request $request,
        string $roasteryId,
        string $productId,
        CatalogAccess $access,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $product = Product::query()
            ->where('roastery_id', $roastery->id)
            ->findOrFail($productId);

        return RoastBatchResource::collection(
            $product->roastBatches()
                ->orderByDesc('roasted_at')
                ->paginate(50),
        );
    }

    public function store(
        StoreRoastBatchRequest $request,
        string $roasteryId,
        string $productId,
        CatalogAccess $access,
        AuditRecorder $audit,
    ): RoastBatchResource {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery);

        $product = Product::query()
            ->where('roastery_id', $roastery->id)
            ->findOrFail($productId);

        $batch = RoastBatch::query()->create([
            ...$request->validated(),
            'product_id' => $product->id,
        ]);

        $audit->record(
            'catalog.roast_batch.created',
            actor: $user,
            auditable: $batch,
            request: $request,
        );

        return new RoastBatchResource($batch);
    }
}
