<?php

use App\Http\Controllers\Admin\AdminContentAuthorController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminOriginController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminRoasteryController;
use App\Http\Controllers\Admin\AdminSeoRedirectController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RequestOtpController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\RoasteryController;
use App\Http\Controllers\Catalog\SearchController;
use App\Http\Controllers\Checkout\CartController;
use App\Http\Controllers\Checkout\CheckoutQuoteController;
use App\Http\Controllers\Checkout\OrderController;
use App\Http\Controllers\Content\ContentController;
use App\Http\Controllers\Content\SeoController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Profile\AddressController;
use App\Http\Controllers\Profile\MeController;
use App\Http\Controllers\Seller\SellerInventoryController;
use App\Http\Controllers\Seller\SellerMediaController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\SellerOriginController;
use App\Http\Controllers\Seller\SellerProductController;
use App\Http\Controllers\Seller\SellerRoastBatchController;
use App\Http\Controllers\Seller\SellerRoasteryController;
use App\Http\Controllers\Seller\SellerVariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])
        ->name('api.v1.health.live');

    Route::get('/health/ready', [HealthController::class, 'ready'])
        ->name('api.v1.health.ready');

    Route::post('/auth/otp/request', RequestOtpController::class)
        ->middleware('throttle:otp-request')
        ->name('api.v1.auth.otp.request');

    Route::post('/auth/otp/verify', VerifyOtpController::class)
        ->middleware('throttle:otp-verify')
        ->name('api.v1.auth.otp.verify');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('api.v1.products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\p{Arabic}_-]+')
        ->name('api.v1.products.show');
    Route::get('/products/{slug}/related', [ProductController::class, 'related'])
        ->where('slug', '[A-Za-z0-9\p{Arabic}_-]+')
        ->name('api.v1.products.related');

    Route::get('/roasteries', [RoasteryController::class, 'index'])
        ->name('api.v1.roasteries.index');
    Route::get('/roasteries/{slug}', [RoasteryController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\p{Arabic}_-]+')
        ->name('api.v1.roasteries.show');

    Route::get('/search', SearchController::class)
        ->name('api.v1.search');

    Route::get('/content', [ContentController::class, 'index'])
        ->name('api.v1.content.index');
    Route::get('/content/resolve', [ContentController::class, 'resolve'])
        ->name('api.v1.content.resolve');
    Route::get('/content/{slug}', [ContentController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\p{Arabic}_-]+')
        ->name('api.v1.content.show');

    Route::get('/seo/indexable', [SeoController::class, 'indexable'])
        ->name('api.v1.seo.indexable');
    Route::get('/seo/redirects/resolve', [SeoController::class, 'redirect'])
        ->name('api.v1.seo.redirects.resolve');

    Route::post('/cart/validate', CartController::class)
        ->middleware('throttle:cart-validate')
        ->name('api.v1.cart.validate');

    Route::middleware(['auth:sanctum', 'rosta.session'])->group(function (): void {
        Route::post('/auth/logout', LogoutController::class)
            ->name('api.v1.auth.logout');

        Route::get('/me', [MeController::class, 'show'])
            ->name('api.v1.me.show');
        Route::patch('/me', [MeController::class, 'update'])
            ->name('api.v1.me.update');

        Route::get('/me/addresses', [AddressController::class, 'index'])
            ->name('api.v1.addresses.index');
        Route::post('/me/addresses', [AddressController::class, 'store'])
            ->name('api.v1.addresses.store');
        Route::patch('/me/addresses/{addressId}', [AddressController::class, 'update'])
            ->where('addressId', '[A-Za-z0-9._:-]+')
            ->name('api.v1.addresses.update');
        Route::delete('/me/addresses/{addressId}', [AddressController::class, 'destroy'])
            ->where('addressId', '[A-Za-z0-9._:-]+')
            ->name('api.v1.addresses.destroy');

        Route::post('/checkout/quote', CheckoutQuoteController::class)
            ->middleware('throttle:checkout-quote')
            ->name('api.v1.checkout.quote');

        Route::get('/orders', [OrderController::class, 'index'])
            ->name('api.v1.orders.index');
        Route::post('/orders', [OrderController::class, 'store'])
            ->middleware('throttle:order-create')
            ->name('api.v1.orders.store');
        Route::get('/orders/{orderId}', [OrderController::class, 'show'])
            ->where('orderId', '[A-Za-z0-9._:-]+')
            ->name('api.v1.orders.show');
        Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel'])
            ->where('orderId', '[A-Za-z0-9._:-]+')
            ->name('api.v1.orders.cancel');

        Route::get('/seller/origins', SellerOriginController::class)
            ->middleware('rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator')
            ->name('api.v1.seller.origins.index');

        Route::post('/seller/roasteries', [SellerRoasteryController::class, 'store'])
            ->name('api.v1.seller.roasteries.store');

        Route::prefix('/seller/roasteries/{roasteryId}')
            ->where(['roasteryId' => '[A-Za-z0-9._:-]+'])
            ->middleware('rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator')
            ->group(function (): void {
                Route::get('/', [SellerRoasteryController::class, 'show'])
                    ->name('api.v1.seller.roasteries.show');
                Route::patch('/', [SellerRoasteryController::class, 'update'])
                    ->name('api.v1.seller.roasteries.update');

                Route::get('/media', [SellerMediaController::class, 'index'])
                    ->name('api.v1.seller.media.index');
                Route::post('/media', [SellerMediaController::class, 'store'])
                    ->name('api.v1.seller.media.store');

                Route::get('/products', [SellerProductController::class, 'index'])
                    ->name('api.v1.seller.products.index');
                Route::post('/products', [SellerProductController::class, 'store'])
                    ->name('api.v1.seller.products.store');
                Route::get('/products/{productId}', [SellerProductController::class, 'show'])
                    ->where('productId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.products.show');
                Route::patch('/products/{productId}', [SellerProductController::class, 'update'])
                    ->where('productId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.products.update');

                Route::post('/products/{productId}/variants', [SellerVariantController::class, 'store'])
                    ->where('productId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.variants.store');
                Route::patch('/products/{productId}/variants/{variantId}', [SellerVariantController::class, 'update'])
                    ->where([
                        'productId' => '[A-Za-z0-9._:-]+',
                        'variantId' => '[A-Za-z0-9._:-]+',
                    ])
                    ->name('api.v1.seller.variants.update');

                Route::get('/products/{productId}/roast-batches', [SellerRoastBatchController::class, 'index'])
                    ->where('productId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.roast_batches.index');
                Route::post('/products/{productId}/roast-batches', [SellerRoastBatchController::class, 'store'])
                    ->where('productId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.roast_batches.store');

                Route::get('/variants/{variantId}/stock-ledger', [SellerInventoryController::class, 'index'])
                    ->where('variantId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.inventory.index');
                Route::post('/variants/{variantId}/stock-adjustments', [SellerInventoryController::class, 'adjust'])
                    ->where('variantId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.inventory.adjust');

                Route::get('/orders', [SellerOrderController::class, 'index'])
                    ->name('api.v1.seller.orders.index');
                Route::get('/orders/{orderId}', [SellerOrderController::class, 'show'])
                    ->where('orderId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.seller.orders.show');
            });

        Route::prefix('/admin')
            ->middleware('rosta.role:administrator')
            ->group(function (): void {
                Route::get('/origins', [AdminOriginController::class, 'index'])
                    ->name('api.v1.admin.origins.index');
                Route::post('/origins', [AdminOriginController::class, 'store'])
                    ->name('api.v1.admin.origins.store');
                Route::patch('/origins/{originId}', [AdminOriginController::class, 'update'])
                    ->where('originId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.origins.update');

                Route::get('/roasteries', [AdminRoasteryController::class, 'index'])
                    ->name('api.v1.admin.roasteries.index');
                Route::patch('/roasteries/{roasteryId}/status', [AdminRoasteryController::class, 'setStatus'])
                    ->where('roasteryId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.roasteries.status');

                Route::get('/products', [AdminProductController::class, 'index'])
                    ->name('api.v1.admin.products.index');
                Route::patch('/products/{productId}/status', [AdminProductController::class, 'setStatus'])
                    ->where('productId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.products.status');

                Route::get('/orders', [AdminOrderController::class, 'index'])
                    ->name('api.v1.admin.orders.index');
                Route::get('/orders/{orderId}', [AdminOrderController::class, 'show'])
                    ->where('orderId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.orders.show');

                Route::get('/content-authors', [AdminContentAuthorController::class, 'index'])
                    ->name('api.v1.admin.content_authors.index');
                Route::post('/content-authors', [AdminContentAuthorController::class, 'store'])
                    ->name('api.v1.admin.content_authors.store');
                Route::patch('/content-authors/{authorId}', [AdminContentAuthorController::class, 'update'])
                    ->where('authorId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.content_authors.update');

                Route::get('/content', [AdminContentController::class, 'index'])
                    ->name('api.v1.admin.content.index');
                Route::post('/content', [AdminContentController::class, 'store'])
                    ->name('api.v1.admin.content.store');
                Route::get('/content/{entryId}', [AdminContentController::class, 'show'])
                    ->where('entryId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.content.show');
                Route::patch('/content/{entryId}', [AdminContentController::class, 'update'])
                    ->where('entryId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.content.update');
                Route::patch('/content/{entryId}/status', [AdminContentController::class, 'setStatus'])
                    ->where('entryId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.content.status');

                Route::get('/seo-redirects', [AdminSeoRedirectController::class, 'index'])
                    ->name('api.v1.admin.seo_redirects.index');
                Route::post('/seo-redirects', [AdminSeoRedirectController::class, 'store'])
                    ->name('api.v1.admin.seo_redirects.store');
                Route::patch('/seo-redirects/{redirectId}', [AdminSeoRedirectController::class, 'update'])
                    ->where('redirectId', '[A-Za-z0-9._:-]+')
                    ->name('api.v1.admin.seo_redirects.update');
            });
    });
});
