<?php

use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\BackofficeController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\StockTransferController;
use App\Http\Middleware\EnsureCentralAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreviewController::class, 'index']);
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store']);

Route::middleware('auth')->group(function () {
	Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
	Route::get('/backoffice', [BackofficeController::class, 'index']);
	Route::get('/inventory', [InventoryController::class, 'index']);
	Route::get('/inventory/transfer', [StockTransferController::class, 'index'])->name('stock-transfer');
	Route::get('/pos', [PosController::class, 'index']);
	Route::get('/reports/journal', [ReportController::class, 'journal']);

	Route::prefix('reports/accounting')->name('reports.accounting.')->group(function () {
		Route::get('/ledger', [AccountingReportController::class, 'ledger'])->name('ledger');
		Route::get('/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('trial-balance');
		Route::get('/income-statement', [AccountingReportController::class, 'incomeStatement'])->name('income-statement');
	});

	Route::prefix('purchases/goods-receipts')->name('purchases.goods-receipts.')->middleware(EnsureCentralAdmin::class)->group(function () {
		Route::get('/', [GoodsReceiptController::class, 'index'])->name('index');
		Route::get('/create', [GoodsReceiptController::class, 'create'])->name('create');
		Route::post('/', [GoodsReceiptController::class, 'store'])->name('store');
		Route::get('/{id}', [GoodsReceiptController::class, 'show'])->name('show');
	});
});

