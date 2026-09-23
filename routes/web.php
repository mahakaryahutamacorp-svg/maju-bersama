<?php

use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\BackofficeController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockCardController;
use App\Http\Controllers\StockTransferPrintController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\StockTransferController;
use App\Http\Controllers\Web\WarehouseController;
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
	Route::get('/pos', [PosController::class, 'index'])->name('pos');
	Route::post('/pos/shift/open', [PosController::class, 'openShift'])->name('pos.shift.open');
	Route::post('/pos/shift/close', [PosController::class, 'closeShift'])->name('pos.shift.close');
	Route::get('/pos/receipt/{receipt_number}', [PosController::class, 'receipt'])->name('pos.receipt');
	Route::get('/reports/journal', [ReportController::class, 'journal']);
	Route::get('/reports/inventory/stock-card', [StockCardController::class, 'index'])->name('reports.inventory.stock-card');

	Route::prefix('inventory/adjustments')->name('inventory.adjustments.')->group(function () {
		Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
		Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');
		Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
		Route::get('/{id}', [StockAdjustmentController::class, 'show'])->name('show');
	});

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

	Route::prefix('backoffice')->name('backoffice.')->group(function () {
		// Products
		Route::resource('products', App\Http\Controllers\Web\ProductController::class)->except(['show']);

		// Categories
		Route::get('categories', [App\Http\Controllers\Web\CategoryController::class, 'index'])->name('categories.index');
		Route::post('categories', [App\Http\Controllers\Web\CategoryController::class, 'store'])->name('categories.store');
		Route::put('categories/{id}', [App\Http\Controllers\Web\CategoryController::class, 'update'])->name('categories.update');
		Route::delete('categories/{id}', [App\Http\Controllers\Web\CategoryController::class, 'destroy'])->name('categories.destroy');

		// Users & Cashiers
		Route::resource('users', App\Http\Controllers\Web\UserController::class)->except(['show']);

		// Branches (Strictly Master only)
		Route::resource('branches', App\Http\Controllers\Web\BranchController::class)->except(['show']);

		// Warehouses (Master: all branches, Branch Admin: own branch only)
		Route::resource('warehouses', WarehouseController::class)->except(['show']);

		// Suppliers
		Route::resource('suppliers', App\Http\Controllers\Web\SupplierController::class);

		// Sales Returns (Retur Penjualan Pelanggan)
		Route::resource('sales-returns', App\Http\Controllers\Web\SalesReturnController::class);

		// Purchase Orders
		Route::resource('purchase-orders', App\Http\Controllers\Web\PurchaseOrderController::class);

		// Purchase Returns (Retur Pembelian)
		Route::resource('purchase-returns', App\Http\Controllers\Web\PurchaseReturnController::class);

		// Supplier Payments
		Route::resource('supplier-payments', App\Http\Controllers\Web\SupplierPaymentController::class);

		// Expense Categories (Master Data Beban)
		Route::resource('expense-categories', App\Http\Controllers\Web\ExpenseCategoryController::class);

		// Expenses (Biaya Operasional / Kas Keluar)
		Route::resource('expenses', App\Http\Controllers\Web\ExpenseController::class);

		// Cash Transfers (Mutasi / Transfer Antar Kas & Bank)
		Route::resource('cash-transfers', App\Http\Controllers\Web\CashTransferController::class);

		// Opening Balances (Setup Saldo Awal Sistem)
		Route::get('opening-balances', [App\Http\Controllers\Web\OpeningBalanceController::class, 'create'])->name('opening-balances.create');
		Route::post('opening-balances', [App\Http\Controllers\Web\OpeningBalanceController::class, 'store'])->name('opening-balances.store');

		// Universal Transaction Viewer (Modal)
		Route::get('transactions/{reference}/details', [App\Http\Controllers\TransactionViewerController::class, 'show'])->name('transactions.details');

		// Report Center (Pusat Laporan)
		Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
	});

	Route::get('/backoffice/stock-transfers/{reference}/print', [StockTransferPrintController::class, 'print'])->name('stock-transfers.print');
	Route::get('/backoffice/transactions/{reference}/details', [App\Http\Controllers\TransactionViewerController::class, 'show'])->name('transactions.details');
	Route::get('/backoffice/reports', [ReportController::class, 'index'])->name('reports.index');
});

