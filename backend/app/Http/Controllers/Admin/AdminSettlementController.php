<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettlementBatchStatus;
use App\Exceptions\ApiDomainException;
use App\Http\Requests\Settlement\CreateSettlementBatchRequest;
use App\Http\Requests\Settlement\ResolveSettlementBatchRequest;
use App\Http\Requests\Settlement\SetSettlementHoldRequest;
use App\Models\Roastery;
use App\Models\RoasterySettlementProfile;
use App\Models\SettlementBatch;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Settlement\SettlementBatchService;
use App\Services\Settlement\SettlementReleaseService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSettlementController
{
    public function index(Request $request, CatalogAccess $access, SettlementBatchService $batches): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $query = SettlementBatch::query()->with(['roastery', 'creator', 'processor'])->latest('created_at');
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, array_column(SettlementBatchStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $roasteryId = trim((string) $request->query('roastery_id', ''));
        if ($roasteryId !== '') {
            $query->where('roastery_id', $roasteryId);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(fn (SettlementBatch $batch): array => $this->payload($batches->loadBatch($batch)))
                ->all(),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function store(
        CreateSettlementBatchRequest $request,
        CatalogAccess $access,
        SettlementBatchService $batches,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $validated = $request->validated();
        $roastery = Roastery::query()->findOrFail($validated['roastery_id']);
        $profile = RoasterySettlementProfile::query()
            ->where('roastery_id', $roastery->id)
            ->first();

        if (! $profile instanceof RoasterySettlementProfile || ! $profile->isVerified()) {
            throw new ApiDomainException(
                'settlement.profile_not_verified',
                'پروفایل مقصد تسویه این روستری هنوز توسط ادمین تأیید نشده است.',
                409,
                ['roastery_id' => [$roastery->id]],
            );
        }

        $batch = $batches->create(
            $user,
            $roastery,
            (string) ($validated['currency'] ?? 'IRR'),
            (string) $validated['idempotency_key'],
            $request,
        );

        return ApiResponse::success($this->payload($batch), 201);
    }

    public function resolve(
        ResolveSettlementBatchRequest $request,
        string $batchId,
        CatalogAccess $access,
        SettlementBatchService $batches,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $batch = $batches->resolve(
            $user,
            SettlementBatch::query()->findOrFail($batchId),
            $request->validated(),
            $request,
        );

        return ApiResponse::success($this->payload($batch));
    }

    public function hold(
        SetSettlementHoldRequest $request,
        string $subOrderId,
        CatalogAccess $access,
        SettlementReleaseService $settlements,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $subOrder = $settlements->setHold(
            $user,
            SubOrder::query()->findOrFail($subOrderId),
            $request->validated(),
            $request,
        );

        return ApiResponse::success([
            'sub_order_id' => $subOrder->id,
            'settlement_hold_code' => $subOrder->settlement_hold_code,
            'settlement_hold_note' => $subOrder->settlement_hold_note,
            'settlement_released_at' => $subOrder->settlement_released_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(SettlementBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'roastery' => [
                'id' => $batch->roastery_id,
                'name' => $batch->roastery?->name,
            ],
            'status' => $batch->status->value,
            'currency' => $batch->currency,
            'gross_total' => $batch->gross_total,
            'discount_total' => $batch->discount_total,
            'tax_total' => $batch->tax_total,
            'net_total' => $batch->net_total,
            'allocation_count' => $batch->allocation_count,
            'payout_reference' => $batch->payout_reference,
            'failure_code' => $batch->failure_code,
            'failure_message' => $batch->failure_message,
            'scheduled_at' => $batch->scheduled_at?->toIso8601String(),
            'processing_at' => $batch->processing_at?->toIso8601String(),
            'paid_at' => $batch->paid_at?->toIso8601String(),
            'failed_at' => $batch->failed_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
            'allocations' => $batch->allocations
                ->map(static fn ($allocation): array => [
                    'id' => $allocation->id,
                    'sub_order_id' => $allocation->sub_order_id,
                    'allocation_type' => $allocation->allocation_type,
                    'net_amount' => $allocation->net_amount,
                    'status' => $allocation->status->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, int> */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
