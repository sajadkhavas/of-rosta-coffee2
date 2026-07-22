<?php

namespace App\Services\Catalog;

use App\Enums\ProductStatus;
use App\Enums\RoasteryStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Product;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CatalogPublicationService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    public function setRoasteryStatus(
        Roastery $roastery,
        RoasteryStatus $status,
        User $actor,
        Request $request,
    ): Roastery {
        return DB::transaction(function () use ($roastery, $status, $actor, $request): Roastery {
            $roastery->forceFill([
                'status' => $status,
                'verified_at' => $status === RoasteryStatus::Verified ? now() : null,
            ])->save();

            $this->audit->record(
                'catalog.roastery.status_changed',
                actor: $actor,
                auditable: $roastery,
                metadata: ['status' => $status->value],
                request: $request,
            );

            return $roastery->refresh();
        });
    }

    public function setProductStatus(
        Product $product,
        ProductStatus $status,
        User $actor,
        Request $request,
    ): Product {
        return DB::transaction(function () use ($product, $status, $actor, $request): Product {
            $product->loadMissing('roastery', 'variants');

            if ($status === ProductStatus::Published) {
                if (! $product->roastery->isPubliclyVisible()) {
                    throw new ApiDomainException(
                        'catalog.roastery_not_verified',
                        'محصول روستری تأییدنشده قابل انتشار نیست.',
                        409,
                    );
                }

                if (! $product->variants->contains(
                    static fn ($variant): bool => $variant->is_active,
                )) {
                    throw new ApiDomainException(
                        'catalog.variant_required',
                        'برای انتشار محصول حداقل یک Variant فعال لازم است.',
                        409,
                    );
                }
            }

            $product->forceFill([
                'status' => $status,
                'published_at' => $status === ProductStatus::Published
                    ? ($product->published_at ?? now())
                    : null,
            ])->save();

            $this->audit->record(
                'catalog.product.status_changed',
                actor: $actor,
                auditable: $product,
                metadata: ['status' => $status->value],
                request: $request,
            );

            return $product->refresh();
        });
    }
}
