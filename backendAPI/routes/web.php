<?php

use App\Http\Controllers\Debug\DebugDashboardController;
use App\Http\Controllers\Debug\DebugAuthController;
use App\Http\Controllers\Debug\DebugInvoiceController;
use App\Http\Controllers\Debug\DebugJournalController;
use App\Http\Controllers\Debug\DebugPaymentController;
use App\Http\Controllers\Debug\DebugReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('debug')->name('debug.')->group(function () {
    Route::get('/login', [DebugAuthController::class, 'login'])->name('login');

    Route::middleware('web')->group(function () {
    Route::get('/', [DebugDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('journals')->name('journals.')->group(function () {
        Route::get('/', [DebugJournalController::class, 'index'])->name('index');
        Route::get('/create', [DebugJournalController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugJournalController::class, 'edit'])->name('edit');
    });

    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [DebugInvoiceController::class, 'index'])->name('index');
        Route::get('/create', [DebugInvoiceController::class, 'create'])->name('create');
    });

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [DebugPaymentController::class, 'index'])->name('index');
        Route::get('/create', [DebugPaymentController::class, 'create'])->name('create');
    });

    Route::get('/trial-balance', [DebugReportController::class, 'trialBalancePage'])->name('trial-balance');
    Route::get('/general-ledger', [DebugReportController::class, 'generalLedger'])->name('general-ledger');
    });

    Route::middleware('auth:sanctum')->prefix('api')->name('api.')->group(function () {
        Route::get('/dashboard-stats', [DebugDashboardController::class, 'stats'])->name('dashboard-stats');

        Route::get('/journals/{id}', [DebugJournalController::class, 'show'])->name('journals.show');

        Route::get('/invoices', [DebugInvoiceController::class, 'list'])->name('invoices.list');
        Route::post('/invoices', [DebugInvoiceController::class, 'store'])->name('invoices.store');

        Route::get('/payments', [DebugPaymentController::class, 'list'])->name('payments.list');
        Route::post('/payments', [DebugPaymentController::class, 'store'])->name('payments.store');
    });
});
