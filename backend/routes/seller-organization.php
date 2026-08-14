<?php

use App\Http\Controllers\Admin\AdminSellerOrganizationController;
use App\Http\Controllers\Seller\SellerOrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
    Route::post('/seller/invitations/accept', [SellerOrganizationController::class, 'acceptInvitation'])
        ->middleware('throttle:api')
        ->name('api.v1.seller.invitations.accept');

    Route::prefix('/seller/roasteries/{roasteryId}')
        ->where(['roasteryId' => '[A-Za-z0-9._:-]+'])
        ->middleware(['throttle:api', 'rosta.seller'])
        ->group(function (): void {
            Route::get('/organization', [SellerOrganizationController::class, 'show'])
                ->name('api.v1.seller.organization.show');
            Route::get('/members', [SellerOrganizationController::class, 'listMembers'])
                ->name('api.v1.seller.members.index');
            Route::patch('/members/{membershipId}', [SellerOrganizationController::class, 'updateMember'])
                ->where('membershipId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.seller.members.update');
            Route::delete('/members/{membershipId}', [SellerOrganizationController::class, 'removeMember'])
                ->where('membershipId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.seller.members.destroy');
            Route::get('/invitations', [SellerOrganizationController::class, 'listInvitations'])
                ->name('api.v1.seller.invitations.index');
            Route::post('/invitations', [SellerOrganizationController::class, 'createInvitation'])
                ->name('api.v1.seller.invitations.store');
            Route::delete('/invitations/{inviteId}', [SellerOrganizationController::class, 'revokeInvitation'])
                ->where('inviteId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.seller.invitations.destroy');
            Route::get('/schedule', [SellerOrganizationController::class, 'showSchedule'])
                ->name('api.v1.seller.schedule.show');
            Route::put('/schedule', [SellerOrganizationController::class, 'updateSchedule'])
                ->name('api.v1.seller.schedule.update');
            Route::get('/closures', [SellerOrganizationController::class, 'listClosures'])
                ->name('api.v1.seller.closures.index');
            Route::post('/closures', [SellerOrganizationController::class, 'createClosure'])
                ->name('api.v1.seller.closures.store');
            Route::delete('/closures/{closureId}', [SellerOrganizationController::class, 'revokeClosure'])
                ->where('closureId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.seller.closures.destroy');
            Route::get('/promotions', [SellerOrganizationController::class, 'listPromotions'])
                ->name('api.v1.seller.promotions.index');
            Route::post('/promotions', [SellerOrganizationController::class, 'createPromotion'])
                ->name('api.v1.seller.promotions.store');
            Route::patch('/promotions/{promotionId}', [SellerOrganizationController::class, 'updatePromotion'])
                ->where('promotionId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.seller.promotions.update');
        });

    Route::prefix('/admin/seller-organizations')
        ->middleware(['rosta.role:administrator', 'throttle:admin-operations'])
        ->group(function (): void {
            Route::get('/roasteries/{roasteryId}', [AdminSellerOrganizationController::class, 'show'])
                ->where('roasteryId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.admin.seller_organizations.show');
            Route::post('/invitations/{inviteId}/revoke', [AdminSellerOrganizationController::class, 'revokeInvitation'])
                ->where('inviteId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.admin.seller_organizations.invitations.revoke');
            Route::patch('/memberships/{membershipId}/lock', [AdminSellerOrganizationController::class, 'setMembershipLock'])
                ->where('membershipId', '[A-Za-z0-9._:-]+')
                ->name('api.v1.admin.seller_organizations.memberships.lock');
        });
});
