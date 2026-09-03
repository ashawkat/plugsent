<?php

use App\Http\Controllers\Connector\PairController;
use App\Http\Controllers\Connector\PollController;
use App\Http\Controllers\Connector\ResultsController;
use Illuminate\Support\Facades\Route;

// Connector protocol v1 — called by the WordPress plugin, server-to-server.
// Not part of the web middleware group: no sessions, no CSRF.
Route::prefix('connector/v1')->group(function (): void {
    Route::post('pair', PairController::class);

    Route::middleware('connector.auth')->group(function (): void {
        Route::post('poll', PollController::class);
        Route::post('results', ResultsController::class);
    });
});
