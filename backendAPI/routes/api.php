<?php

use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Auth\TokenAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [TokenAuthController::class, 'issueToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [TokenAuthController::class, 'logout']);
    Route::get('/journals', [JournalController::class, 'index']);
    Route::post('/journals', [JournalController::class, 'store']);
    Route::put('/journals/{id}', [JournalController::class, 'update']);
    Route::post('/journals/{id}/void', [JournalController::class, 'void']);
    Route::post('/journals/{id}/post', [JournalController::class, 'post']);

    Route::prefix('reports')->group(function () {
        Route::get('/general-ledger', [ReportController::class, 'generalLedger']);
        Route::get('/trial-balance', [ReportController::class, 'trialBalance']);
    });
});
