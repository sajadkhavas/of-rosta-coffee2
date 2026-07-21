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

final class SellerRoastBatchController
{
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
