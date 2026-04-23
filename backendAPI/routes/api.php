<?php

use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\JournalSettingsController;
use App\Http\Controllers\Accounting\ItemController;
use App\Http\Controllers\Accounting\InvoiceController;
use App\Http\Controllers\Accounting\PurchaseInvoiceController;
use App\Http\Controllers\Accounting\PurchasePaymentController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\StockController;
use App\Http\Controllers\Auth\TokenAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [TokenAuthController::class, 'issueToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [TokenAuthController::class, 'logout']);
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);
    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::get('/items/{id}/history', [ItemController::class, 'history']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{id}/post', [InvoiceController::class, 'post']);

    Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index']);
    Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store']);
    Route::get('/purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show']);
    Route::post('/purchase-invoices/{id}/post', [PurchaseInvoiceController::class, 'post']);
    Route::post('/purchase-invoices/{id}/payments', [PurchaseInvoiceController::class, 'recordPayment']);

    Route::get('/purchase-payments', [PurchasePaymentController::class, 'index']);
    Route::post('/purchase-payments', [PurchasePaymentController::class, 'store']);
    Route::get('/purchase-payments/{id}', [PurchasePaymentController::class, 'show']);

    Route::get('/warehouses', [StockController::class, 'warehousesIndex']);
    Route::post('/warehouses', [StockController::class, 'warehousesStore']);
    Route::get('/warehouses/{id}', [StockController::class, 'warehousesShow']);
    Route::put('/warehouses/{id}', [StockController::class, 'warehousesUpdate']);

    Route::post('/stocks/adjustment', [StockController::class, 'adjustment']);
    Route::post('/stocks/purchase', [StockController::class, 'purchase']);
    Route::post('/stocks/transfer', [StockController::class, 'transfer']);
    Route::get('/settings/journals', [JournalSettingsController::class, 'show']);
    Route::put('/settings/journals', [JournalSettingsController::class, 'update']);
    Route::get('/journals', [JournalController::class, 'index']);
    Route::post('/journals', [JournalController::class, 'store']);
    Route::put('/journals/{id}', [JournalController::class, 'update']);
    Route::post('/journals/{id}/void', [JournalController::class, 'void']);
    Route::post('/journals/{id}/post', [JournalController::class, 'post']);

    Route::prefix('reports')->group(function () {
        Route::get('/general-ledger', [ReportController::class, 'generalLedger']);
        Route::get('/trial-balance', [ReportController::class, 'trialBalance']);
        Route::get('/profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet']);
        Route::get('/cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('/stock-card', [ReportController::class, 'stockCard']);
    });
});
