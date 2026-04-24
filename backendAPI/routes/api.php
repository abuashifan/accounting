<?php

use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\CustomerController;
use App\Http\Controllers\Accounting\VendorController;
use App\Http\Controllers\Accounting\JournalSettingsController;
use App\Http\Controllers\Accounting\ItemController;
use App\Http\Controllers\Accounting\InvoiceController;
use App\Http\Controllers\Accounting\PaymentController;
use App\Http\Controllers\Accounting\PurchaseInvoiceController;
use App\Http\Controllers\Accounting\PurchasePaymentController;
use App\Http\Controllers\Accounting\PurchaseReturnController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\SalesReturnController;
use App\Http\Controllers\Accounting\StockController;
use App\Http\Controllers\Auth\TokenAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [TokenAuthController::class, 'issueToken']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [TokenAuthController::class, 'logout']);
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/accounts/{id}', [AccountController::class, 'show']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);
    Route::delete('/accounts/{id}', [AccountController::class, 'destroy']);

    // routes/api.php - tambahkan setelah route accounts

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    Route::get('/vendors', [VendorController::class, 'index']);
    Route::post('/vendors', [VendorController::class, 'store']);
    Route::get('/vendors/{id}', [VendorController::class, 'show']);
    Route::put('/vendors/{id}', [VendorController::class, 'update']);
    Route::delete('/vendors/{id}', [VendorController::class, 'destroy']);

    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);
    Route::get('/items/{id}/history', [ItemController::class, 'history']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::post('/invoices/{id}/post', [InvoiceController::class, 'post']);
    Route::post('/invoices/{id}/void', [InvoiceController::class, 'void']);
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::put('/payments/{id}', [PaymentController::class, 'update']);
    Route::post('/payments/{id}/void', [PaymentController::class, 'void']);
    Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);

    Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index']);
    Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store']);
    Route::get('/purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show']);
    Route::put('/purchase-invoices/{id}', [PurchaseInvoiceController::class, 'update']);
    Route::post('/purchase-invoices/{id}/post', [PurchaseInvoiceController::class, 'post']);
    Route::post('/purchase-invoices/{id}/void', [PurchaseInvoiceController::class, 'void']);
    Route::delete('/purchase-invoices/{id}', [PurchaseInvoiceController::class, 'destroy']);
    Route::post('/purchase-invoices/{id}/payments', [PurchaseInvoiceController::class, 'recordPayment']);

    Route::get('/purchase-payments', [PurchasePaymentController::class, 'index']);
    Route::post('/purchase-payments', [PurchasePaymentController::class, 'store']);
    Route::get('/purchase-payments/{id}', [PurchasePaymentController::class, 'show']);
    Route::put('/purchase-payments/{id}', [PurchasePaymentController::class, 'update']);
    Route::post('/purchase-payments/{id}/void', [PurchasePaymentController::class, 'void']);
    Route::delete('/purchase-payments/{id}', [PurchasePaymentController::class, 'destroy']);

    Route::get('/sales-returns', [SalesReturnController::class, 'index']);
    Route::post('/sales-returns', [SalesReturnController::class, 'store']);
    Route::get('/sales-returns/{id}', [SalesReturnController::class, 'show']);
    Route::put('/sales-returns/{id}', [SalesReturnController::class, 'update']);
    Route::delete('/sales-returns/{id}', [SalesReturnController::class, 'destroy']);
    Route::post('/sales-returns/{id}/post', [SalesReturnController::class, 'post']);
    Route::post('/sales-returns/{id}/void', [SalesReturnController::class, 'void']);

    Route::get('/purchase-returns', [PurchaseReturnController::class, 'index']);
    Route::post('/purchase-returns', [PurchaseReturnController::class, 'store']);
    Route::get('/purchase-returns/{id}', [PurchaseReturnController::class, 'show']);
    Route::put('/purchase-returns/{id}', [PurchaseReturnController::class, 'update']);
    Route::delete('/purchase-returns/{id}', [PurchaseReturnController::class, 'destroy']);
    Route::post('/purchase-returns/{id}/post', [PurchaseReturnController::class, 'post']);
    Route::post('/purchase-returns/{id}/void', [PurchaseReturnController::class, 'void']);

    Route::get('/warehouses', [StockController::class, 'warehousesIndex']);
    Route::post('/warehouses', [StockController::class, 'warehousesStore']);
    Route::get('/warehouses/{id}', [StockController::class, 'warehousesShow']);
    Route::put('/warehouses/{id}', [StockController::class, 'warehousesUpdate']);
    Route::delete('/warehouses/{id}', [StockController::class, 'warehousesDestroy']);

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
