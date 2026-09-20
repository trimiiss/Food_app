<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\ShopSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — version 1
|--------------------------------------------------------------------------
| Everything is served under /api/v1. Versioning from day one costs nothing
| and means a breaking change later can ship as /api/v2 without stranding
| existing clients.
|
|   public         -> anyone
|   auth:sanctum   -> any signed-in user (customer or admin)
|   admin          -> signed-in users with role=admin (401 if anonymous, 403 if customer)
*/

Route::prefix('v1')->group(function () {

    // ---- Authentication -------------------------------------------------
    // Throttled to slow down credential stuffing / brute force.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('admin/register', [AuthController::class, 'registerAdmin']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // ---- Public storefront ----------------------------------------------
    Route::get('shop', ShopSettingsController::class);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product:slug}', [ProductController::class, 'show']);
    // Publicly advertised promo codes for the Deals page.
    Route::get('promotions', [PromotionController::class, 'index']);

    // Prices a cart (offers + promo code) without creating an order. Throttled:
    // it is also the only endpoint that tells you whether a code is valid.
    Route::post('cart/preview', [CartController::class, 'preview'])->middleware('throttle:60,1');

    // ---- Signed-in users --------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Checkout + the customer's own order history. Ownership is enforced
        // by OrderPolicy (someone else's order is a 404, not a 403).
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    });

    // ---- Admin panel ------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('stats', Admin\DashboardController::class)->name('stats');

            Route::apiResource('categories', Admin\CategoryController::class);
            Route::apiResource('products', Admin\ProductController::class);
            Route::apiResource('promo-codes', Admin\PromoCodeController::class);

            Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
        });
});
