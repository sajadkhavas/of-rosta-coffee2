<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundAttempt;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Refunds\RefundDispatchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminDispatchRefundController extends Controller
{
    public function __invoke(
        Request $request,
        string $refundId,
        CatalogAccess $access,
        RefundDispatchService $dispatch,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $refund = $dispatch->dispatch(
            $user,
            RefundAttempt::query()->findOrFail($refundId),
            $request,
        );

        return ApiResponse::success([
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'payment_attempt_id' => $refund->payment_attempt_id,
            'status' => $refund->status->value,
            'provider' => $refund->provider,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'provider_reference' => $refund->provider_reference,
            'provider_code' => $refund->provider_code,
            'failure_code' => $refund->failure_code,
            'failure_message' => $refund->failure_message,
            'processing_at' => $refund->processing_at?->toIso8601String(),
            'succeeded_at' => $refund->succeeded_at?->toIso8601String(),
        ]);
    }
}
