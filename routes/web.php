<?php

use App\Http\Controllers\Accurate\AccurateConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Unauthenticated on purpose for now — no backoffice/admin auth exists yet
// (Fase 2 of PLANNING.md). Must be gated behind admin middleware before any
// public deploy.
Route::prefix('accurate')->group(function () {
    Route::get('connect', [AccurateConnectionController::class, 'redirectToAuthorize']);
    Route::get('callback', [AccurateConnectionController::class, 'handleCallback']);
    Route::get('databases', [AccurateConnectionController::class, 'listDatabases']);
    Route::post('databases/select', [AccurateConnectionController::class, 'selectDatabase']);
    Route::get('status', [AccurateConnectionController::class, 'status']);
});
