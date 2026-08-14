<?php

use App\Http\Controllers\Admin\AdminDispatchRefundController;
use App\Http\Controllers\Admin\AdminFinanceController;
use App\Http\Controllers\Admin\AdminSettlementController;
use App\Http\Controllers\Seller\SellerSettlementController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'rosta.session',
    'rosta.role:administrator',
    'throttle:admin-operations',
])->group(function (): void {
    Route::get('/admin/finance/refunds', [AdminFinanceController::class, 'refunds'])
        ->name('api.v1.admin.finance.refunds.index');
    Route::get('/admin/finance/reconciliation', [AdminFinanceController::class, 'reconciliation'])
        ->name('api.v1.admin.finance.reconciliation.index');

    Route::get('/admin/finance/settlement-batches', [AdminSettlementController::class, 'index'])
        ->name('api.v1.admin.finance.settlement_batches.index');
    Route::post('/admin/finance/settlement-batches', [AdminSettlementController::class, 'store'])
        ->name('api.v1.admin.finance.settlement_batches.store');
    Route::post('/admin/finance/settlement-batches/{batchId}/resolve', [AdminSettlementController::class, 'resolve'])
        ->where('batchId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.finance.settlement_batches.resolve');
    Route::post('/admin/sub-orders/{subOrderId}/settlement-hold', [AdminSettlementController::class, 'hold'])
        ->where('subOrderId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.finance.settlement_hold');

    Route::post('/admin/orders/{orderId}/refunds', [AdminFinanceController::class, 'requestRefund'])
        ->where('orderId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.refunds.store');
    Route::post('/admin/refunds/{refundId}/approve', [AdminFinanceController::class, 'approveRefund'])
        ->where('refundId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.refunds.approve');
    Route::post('/admin/refunds/{refundId}/dispatch', AdminDispatchRefundController::class)
        ->where('refundId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.refunds.dispatch');
    Route::post('/admin/refunds/{refundId}/resolve', [AdminFinanceController::class, 'resolveRefund'])
        ->where('refundId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.refunds.resolve');

    Route::patch(
        '/admin/finance/reconciliation/{caseId}',
        [AdminFinanceController::class, 'resolveCase'],
    )
        ->where('caseId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.finance.reconciliation.resolve');
});

Route::middleware([
    'auth:sanctum',
    'rosta.session',
    'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator',
    'rosta.seller',
    'throttle:admin-operations',
])->get(
    '/seller/roasteries/{roasteryId}/settlements',
    [SellerSettlementController::class, 'index'],
)->where('roasteryId', '[A-Za-z0-9._:-]+')
    ->name('api.v1.seller.settlements.index');
