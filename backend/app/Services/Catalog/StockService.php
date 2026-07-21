<?php

namespace App\Services\Catalog;

use App\Exceptions\ApiDomainException;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StockService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function adjust(
        ProductVariant $variant,
        User $actor,
        int $delta,
        string $reason,
        ?string $roastBatchId,
        ?string $idempotencyKey,
        array $metadata,
        Request $request,
    ): StockLedgerEntry {
        $metadata = $this->canonicalize($metadata);

        return DB::transaction(function () use (
            $variant,
            $actor,
            $delta,
            $reason,
            $roastBatchId,
            $idempotencyKey,
            $metadata,
            $request,
        ): StockLedgerEntry {
            /** @var ProductVariant $locked */
            $locked = ProductVariant::query()
                ->with('product')
                ->lockForUpdate()
                ->findOrFail($variant->id);

            if ($idempotencyKey !== null) {
                $existing = StockLedgerEntry::query()
                    ->where('variant_id', $locked->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof StockLedgerEntry) {
                    $samePayload = $existing->delta === $delta
                        && $existing->reason->value === $reason
                        && $existing->roast_batch_id === $roastBatchId
                        && ($existing->metadata ?? []) === $metadata;

                    if (! $samePayload) {
                        throw new ApiDomainException(
                            'inventory.idempotency_conflict',
                            'این کلید Idempotency قبلاً با محتوای دیگری استفاده شده است.',
                            409,
                        );
                    }

                    return $existing;
                }
            }

            $batch = null;
            if ($roastBatchId !== null) {
                $batch = RoastBatch::query()
                    ->where('id', $roastBatchId)
                    ->where('product_id', $locked->product_id)
                    ->where('is_active', true)
                    ->first();

                if (! $batch instanceof RoastBatch) {
                    throw new ApiDomainException(
                        'catalog.roast_batch_invalid',
                        'بچ رست برای این محصول معتبر نیست.',
                        422,
                    );
                }
            }

            $balance = $locked->stock_on_hand + $delta;
            if ($balance < 0) {
                throw new ApiDomainException(
                    'inventory.insufficient_stock',
                    'موجودی نمی‌تواند منفی شود.',
                    409,
                );
            }

            if ($balance < $locked->stock_reserved) {
                throw new ApiDomainException(
                    'inventory.reserved_stock_conflict',
                    'موجودی کل نمی‌تواند از موجودی رزروشده کمتر شود.',
                    409,
                );
            }

            $locked->forceFill(['stock_on_hand' => $balance])->save();

            $entry = StockLedgerEntry::query()->create([
                'variant_id' => $locked->id,
                'roast_batch_id' => $batch?->id,
                'actor_id' => $actor->id,
                'delta' => $delta,
                'balance_after' => $balance,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
            ]);

            $this->audit->record(
                'catalog.stock.adjusted',
                actor: $actor,
                auditable: $locked,
                metadata: [
                    'delta' => $delta,
                    'balance_after' => $balance,
                    'reason' => $reason,
                    'roast_batch_id' => $batch?->id,
                ],
                request: $request,
            );

            return $entry;
        });
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(
                        fn (mixed $child): mixed => is_array($child)
                            ? $this->canonicalize($child)
                            : $child,
                        $item,
                    )
                    : $this->canonicalize($item);
            }
        }

        return $value;
    }
}
