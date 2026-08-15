<?php

use App\Http\Controllers\Accurate\AccurateConnectionController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SyncController as AdminSyncController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Webhooks\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

// Public catalog — read-only, no auth.
Route::prefix('catalog')->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('products/{product}/related', [ProductController::class, 'related']);
    Route::get('categories', [ProductController::class, 'categories']);
});

// Public customer auth.
Route::prefix('auth')->group(function () {
    Route::post('register', [CustomerAuthController::class, 'register']);
    Route::post('login', [CustomerAuthController::class, 'login']);
});

// Public payment gateway webhooks — verified by signature, not auth.
Route::post('webhooks/midtrans', MidtransWebhookController::class)->middleware('throttle:30,1');

// Customer-only — token must belong to a Customer, not an admin User.
Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::post('auth/logout', [CustomerAuthController::class, 'logout']);

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('{order}', [OrderController::class, 'show']);
        Route::post('{order}/pay', [OrderController::class, 'pay']);
    });
});

// Admin (mr-twin-backoffice) — token must belong to a User, not a Customer.
Route::prefix('admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);

        // connect/callback stay unauthenticated in routes/web.php — they're
        // full-page browser redirects that can't carry a Bearer token, and
        // the real gate is Accurate's own login. These three, which read or
        // change connection state, are the ones that actually need admin auth.
        Route::prefix('accurate')->group(function () {
            Route::get('databases', [AccurateConnectionController::class, 'listDatabases']);
            Route::post('databases/select', [AccurateConnectionController::class, 'selectDatabase']);
            Route::get('status', [AccurateConnectionController::class, 'status']);
        });

        Route::prefix('sync')->group(function () {
            Route::post('items', [AdminSyncController::class, 'syncItems']);
            Route::post('categories', [AdminSyncController::class, 'syncCategories']);
            Route::get('logs', [AdminSyncController::class, 'logs']);
            Route::get('status', [AdminSyncController::class, 'status']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [AdminProductController::class, 'index']);
            Route::get('{item}', [AdminProductController::class, 'show']);
            Route::put('{item}', [AdminProductController::class, 'update']);
            Route::post('{item}/ai-draft', [AdminProductController::class, 'aiDraft']);
            Route::post('{item}/images', [AdminProductController::class, 'uploadImage']);
            Route::delete('{item}/images', [AdminProductController::class, 'deleteImage']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('{order}', [AdminOrderController::class, 'show']);
            Route::patch('{order}', [AdminOrderController::class, 'update']);
        });
    });
});
