<?php

use App\Http\Controllers\Accurate\AccurateConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Unauthenticated by design — these are full-page browser redirects that
// can't carry a Bearer token, and the real gate is Accurate's own login (see
// routes/api.php's admin group for the rest of the connection endpoints,
// which do read/change connection state and are gated behind admin auth).
Route::prefix('accurate')->group(function () {
    Route::get('connect', [AccurateConnectionController::class, 'redirectToAuthorize']);
    Route::get('callback', [AccurateConnectionController::class, 'handleCallback']);
});
