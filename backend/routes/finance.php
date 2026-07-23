<?php

use App\Http\Controllers\Admin\AdminFinanceController;
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

    Route::post('/admin/orders/{orderId}/refunds', [AdminFinanceController::class, 'requestRefund'])
        ->where('orderId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.refunds.store');
    Route::post('/admin/refunds/{refundId}/approve', [AdminFinanceController::class, 'approveRefund'])
        ->where('refundId', '[A-Za-z0-9._:-]+')
        ->name('api.v1.admin.refunds.approve');
    Route::post('/admin/refunds/{refundId}/dispatch', [AdminFinanceController::class, 'dispatchRefund'])
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
