<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Customer\OrderController;
use Illuminate\Support\Facades\Route;

// Public catalog — read-only, no auth.
Route::prefix('catalog')->group(function () {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [ProductController::class, 'categories']);
});

// Public customer auth.
Route::prefix('auth')->group(function () {
    Route::post('register', [CustomerAuthController::class, 'register']);
    Route::post('login', [CustomerAuthController::class, 'login']);
});

// Customer-only — token must belong to a Customer, not an admin User.
Route::middleware(['auth:sanctum', 'customer'])->group(function () {
    Route::post('auth/logout', [CustomerAuthController::class, 'logout']);

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('{order}', [OrderController::class, 'show']);
    });
});
