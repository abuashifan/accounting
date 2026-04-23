<?php

use App\Http\Controllers\Debug\DebugDashboardController;
use App\Http\Controllers\Debug\DebugAuthController;
use App\Http\Controllers\Debug\DebugAccountController;
use App\Http\Controllers\Debug\DebugInventoryController;
use App\Http\Controllers\Debug\DebugInvoiceController;
use App\Http\Controllers\Debug\DebugJournalController;
use App\Http\Controllers\Debug\DebugPaymentController;
use App\Http\Controllers\Debug\DebugPurchaseInvoiceController;
use App\Http\Controllers\Debug\DebugPurchasePaymentController;
use App\Http\Controllers\Debug\DebugPurchaseReturnController;
use App\Http\Controllers\Debug\DebugPurchaseController;
use App\Http\Controllers\Debug\DebugReportController;
use App\Http\Controllers\Debug\DebugSalesReturnController;
use App\Http\Controllers\Debug\DebugSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('debug')->name('debug.')->group(function () {
    Route::get('/login', [DebugAuthController::class, 'login'])->name('login');

    Route::middleware('web')->group(function () {
    Route::get('/', [DebugDashboardController::class, 'index'])->name('dashboard');

    Route::get('/accounts', [DebugAccountController::class, 'index'])->name('accounts');
    Route::get('/accounts/create', [DebugAccountController::class, 'create'])->name('accounts.create');
    Route::get('/accounts/{id}/edit', [DebugAccountController::class, 'edit'])->name('accounts.edit');

    Route::prefix('journals')->name('journals.')->group(function () {
        Route::get('/', [DebugJournalController::class, 'index'])->name('index');
        Route::get('/create', [DebugJournalController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugJournalController::class, 'edit'])->name('edit');
    });

    Route::get('/settings/journals', [DebugSettingsController::class, 'journalSettings'])->name('settings.journals');

    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [DebugInvoiceController::class, 'index'])->name('index');
        Route::get('/create', [DebugInvoiceController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugInvoiceController::class, 'edit'])->name('edit');
    });

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [DebugPaymentController::class, 'index'])->name('index');
        Route::get('/create', [DebugPaymentController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugPaymentController::class, 'edit'])->name('edit');
    });

    Route::prefix('sales-returns')->name('sales-returns.')->group(function () {
        Route::get('/', [DebugSalesReturnController::class, 'index'])->name('index');
        Route::get('/create', [DebugSalesReturnController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugSalesReturnController::class, 'edit'])->name('edit');
    });

    Route::prefix('purchase-invoices')->name('purchase-invoices.')->group(function () {
        Route::get('/', [DebugPurchaseInvoiceController::class, 'index'])->name('index');
        Route::get('/create', [DebugPurchaseInvoiceController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugPurchaseInvoiceController::class, 'edit'])->name('edit');
    });

    Route::prefix('purchase-payments')->name('purchase-payments.')->group(function () {
        Route::get('/', [DebugPurchasePaymentController::class, 'index'])->name('index');
        Route::get('/create', [DebugPurchasePaymentController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugPurchasePaymentController::class, 'edit'])->name('edit');
    });

    Route::prefix('purchase-returns')->name('purchase-returns.')->group(function () {
        Route::get('/', [DebugPurchaseReturnController::class, 'index'])->name('index');
        Route::get('/create', [DebugPurchaseReturnController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [DebugPurchaseReturnController::class, 'edit'])->name('edit');
    });

    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [DebugPurchaseController::class, 'index'])->name('index');
        Route::get('/create', [DebugPurchaseController::class, 'create'])->name('create');
        Route::get('/{id}/pay', [DebugPurchaseController::class, 'pay'])->name('pay');
    });

    Route::get('/trial-balance', [DebugReportController::class, 'trialBalancePage'])->name('trial-balance');
    Route::get('/general-ledger', [DebugReportController::class, 'generalLedger'])->name('general-ledger');
    Route::get('/profit-loss', [DebugReportController::class, 'profitLossPage'])->name('profit-loss');
    Route::get('/balance-sheet', [DebugReportController::class, 'balanceSheetPage'])->name('balance-sheet');
    Route::get('/cash-flow', [DebugReportController::class, 'cashFlowPage'])->name('cash-flow');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/items', [DebugInventoryController::class, 'items'])->name('items');
        Route::get('/items/create', [DebugInventoryController::class, 'itemsCreate'])->name('items.create');
        Route::get('/items/{id}/edit', [DebugInventoryController::class, 'itemsEdit'])->name('items.edit');
        Route::get('/warehouses', [DebugInventoryController::class, 'warehouses'])->name('warehouses');
        Route::get('/warehouses/create', [DebugInventoryController::class, 'warehousesCreate'])->name('warehouses.create');
        Route::get('/warehouses/{id}/edit', [DebugInventoryController::class, 'warehousesEdit'])->name('warehouses.edit');
        Route::get('/stock-card', [DebugInventoryController::class, 'stockCard'])->name('stock-card');
        Route::get('/adjustment', [DebugInventoryController::class, 'adjustment'])->name('adjustment');
        Route::get('/transfer', [DebugInventoryController::class, 'transfer'])->name('transfer');
    });
    });

    Route::middleware('auth:sanctum')->prefix('api')->name('api.')->group(function () {
        Route::get('/dashboard-stats', [DebugDashboardController::class, 'stats'])->name('dashboard-stats');

        Route::get('/journals/{id}', [DebugJournalController::class, 'show'])->name('journals.show');

        Route::get('/invoices', [DebugInvoiceController::class, 'list'])->name('invoices.list');
        Route::post('/invoices', [DebugInvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{id}/post', [DebugInvoiceController::class, 'post'])->name('invoices.post');

        Route::get('/payments', [DebugPaymentController::class, 'list'])->name('payments.list');
        Route::post('/payments', [DebugPaymentController::class, 'store'])->name('payments.store');
    });
});
