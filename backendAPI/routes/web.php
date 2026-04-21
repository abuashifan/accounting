<?php

use App\Http\Controllers\Debug\DebugReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('debug')->name('debug.')->group(function () {
    Route::redirect('/', '/debug/general-ledger');
    Route::get('/general-ledger', [DebugReportController::class, 'generalLedger'])->name('general-ledger');
    Route::get('/trial-balance', [DebugReportController::class, 'trialBalance'])->name('trial-balance');
});
