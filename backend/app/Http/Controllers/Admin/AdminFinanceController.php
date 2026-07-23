<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReconciliationStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateRefundRequest;
use App\Http\Requests\Finance\ResolveReconciliationRequest;
use App\Http\Requests\Finance\ResolveRefundRequest;
use App\Models\FinancialReconciliationCase;
use App\Models\Order;
use App\Models\RefundAttempt;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Finance\FinancialReconciliationService;
use App\Services\Refunds\RefundService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminFinanceController extends Controller
{
    public function refunds(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $query = RefundAttempt::query()
            ->with(['order', 'paymentAttempt', 'requester', 'approver', 'resolver'])
            ->latest('created_at');
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, array_column(RefundStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(fn (RefundAttempt $refund): array => $this->refundPayload($refund))
                ->all(),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function reconciliation(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $query = FinancialReconciliationCase::query()
            ->with(['order', 'paymentAttempt', 'refundAttempt', 'opener', 'resolver'])
            ->latest('created_at');
        $status = trim((string) $request->query('status', 'open'));
        if (in_array($status, array_column(ReconciliationStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(fn (FinancialReconciliationCase $case): array => $this->casePayload($case))
                ->all(),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function requestRefund(
        CreateRefundRequest $request,
        string $orderId,
        CatalogAccess $access,
        RefundService $refunds,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $refund = $refunds->request(
            $user,
            Order::query()->findOrFail($orderId),
            $request->validated(),
            $request,
        );

        return ApiResponse::success($this->refundPayload($refund->load([
            'order', 'paymentAttempt', 'requester', 'approver', 'resolver',
        ])), 201);
    }

    public function approveRefund(
        Request $request,
        string $refundId,
        CatalogAccess $access,
        RefundService $refunds,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $refund = $refunds->approve(
            $user,
            RefundAttempt::query()->findOrFail($refundId),
            $request,
        );

        return ApiResponse::success($this->refundPayload($refund->load([
            'order', 'paymentAttempt', 'requester', 'approver', 'resolver',
        ])));
    }

    public function resolveRefund(
        ResolveRefundRequest $request,
        string $refundId,
        CatalogAccess $access,
        RefundService $refunds,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $refund = $refunds->resolveManual(
            $user,
            RefundAttempt::query()->findOrFail($refundId),
            $request->validated(),
            $request,
        );

        return ApiResponse::success($this->refundPayload($refund->load([
            'order', 'paymentAttempt', 'requester', 'approver', 'resolver',
        ])));
    }

    public function resolveCase(
        ResolveReconciliationRequest $request,
        string $caseId,
        CatalogAccess $access,
        FinancialReconciliationService $reconciliation,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $case = $reconciliation->resolve(
            $user,
            FinancialReconciliationCase::query()->findOrFail($caseId),
            ReconciliationStatus::from((string) $request->validated('status')),
            (string) $request->validated('resolution'),
            $request,
        );

        return ApiResponse::success($this->casePayload($case->load([
            'order', 'paymentAttempt', 'refundAttempt', 'opener', 'resolver',
        ])));
    }

    /** @return array<string, mixed> */
    private function refundPayload(RefundAttempt $refund): array
    {
        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'order_number' => $refund->order?->order_number,
            'payment_attempt_id' => $refund->payment_attempt_id,
            'payment_reference_id' => $refund->paymentAttempt?->reference_id,
            'status' => $refund->status->value,
            'provider' => $refund->provider,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'reason' => $refund->reason,
            'provider_reference' => $refund->provider_reference,
            'provider_code' => $refund->provider_code,
            'failure_code' => $refund->failure_code,
            'failure_message' => $refund->failure_message,
            'requested_by' => $refund->requested_by,
            'approved_by' => $refund->approved_by,
            'resolved_by' => $refund->resolved_by,
            'approved_at' => $refund->approved_at?->toIso8601String(),
            'processing_at' => $refund->processing_at?->toIso8601String(),
            'succeeded_at' => $refund->succeeded_at?->toIso8601String(),
            'failed_at' => $refund->failed_at?->toIso8601String(),
            'cancelled_at' => $refund->cancelled_at?->toIso8601String(),
            'created_at' => $refund->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function casePayload(FinancialReconciliationCase $case): array
    {
        return [
            'id' => $case->id,
            'order_id' => $case->order_id,
            'order_number' => $case->order?->order_number,
            'payment_attempt_id' => $case->payment_attempt_id,
            'refund_attempt_id' => $case->refund_attempt_id,
            'kind' => $case->kind,
            'status' => $case->status->value,
            'severity' => $case->severity,
            'summary' => $case->summary,
            'details' => $case->details,
            'resolution' => $case->resolution,
            'opened_by' => $case->opened_by,
            'resolved_by' => $case->resolved_by,
            'opened_at' => $case->opened_at?->toIso8601String(),
            'resolved_at' => $case->resolved_at?->toIso8601String(),
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
