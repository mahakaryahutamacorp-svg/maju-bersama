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
use App\Http\Controllers\TransactionViewerController;
use App\Http\Controllers\Web\AssistantController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BackupController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\CashTransactionController;
use App\Http\Controllers\Web\CashTransferController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\ExpenseCategoryController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\FinanceDashboardController;
use App\Http\Controllers\Web\FixedAssetController;
use App\Http\Controllers\Web\InternalMessageController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\OpeningBalanceController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\PurchasePayableController;
use App\Http\Controllers\Web\PurchaseReturnController;
use App\Http\Controllers\Web\SalesReturnController;
use App\Http\Controllers\Web\StockTransferController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\SupplierPaymentController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WarehouseController;
use App\Http\Middleware\EnsureCentralAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store']);

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/backoffice', [BackofficeController::class, 'index']);
    Route::get('/dashboard', [BackofficeController::class, 'index'])->name('dashboard');

    // POS Kasir Operasional (Semua role terotentikasi)
    Route::get('/pos', [PosController::class, 'index'])->name('pos');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::post('/pos/shift/open', [PosController::class, 'openShift'])->name('pos.shift.open');
    Route::post('/pos/shift/close', [PosController::class, 'closeShift'])->name('pos.shift.close');
    Route::get('/pos/receipt/{receipt_number}', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');

    Route::middleware('can:manage-branch-operations')->group(function () {
        Route::post('/fixed-assets/run-depreciation', [FixedAssetController::class, 'runDepreciation']);
    });

    // Divisi 2: Gudang & Inventaris (Master & Branch Admin)
    Route::middleware('can:access-inventory')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/transfer', [StockTransferController::class, 'index'])->name('stock-transfer');

        Route::prefix('inventory/adjustments')->name('inventory.adjustments.')->group(function () {
            Route::get('/', [StockAdjustmentController::class, 'index'])->name('index');
            Route::get('/create', [StockAdjustmentController::class, 'create'])->name('create');
            Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
            Route::get('/{id}', [StockAdjustmentController::class, 'show'])->name('show');
        });

        Route::prefix('purchases/goods-receipts')->name('purchases.goods-receipts.')->middleware(EnsureCentralAdmin::class)->group(function () {
            Route::get('/', [GoodsReceiptController::class, 'index'])->name('index');
            Route::get('/create', [GoodsReceiptController::class, 'create'])->name('create');
            Route::post('/', [GoodsReceiptController::class, 'store'])->name('store');
            Route::get('/{id}', [GoodsReceiptController::class, 'show'])->name('show');
        });
    });

    // Divisi Keuangan & Akuntansi Enterprise + Laporan Konsolidasi (HANYA Master)
    Route::middleware('can:view-accounting')->group(function () {
        Route::get('/reports/journal', [ReportController::class, 'journal']);
        Route::get('/reports/inventory/stock-card', [StockCardController::class, 'index'])->name('reports.inventory.stock-card');

        Route::prefix('reports/accounting')->name('reports.accounting.')->group(function () {
            Route::get('/ledger', [AccountingReportController::class, 'ledger'])->name('ledger');
            Route::get('/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/income-statement', [AccountingReportController::class, 'incomeStatement'])->name('income-statement');
        });

        // Hutang Distributor (Accounts Payable)
        Route::prefix('purchases/payables')->name('purchases.payables')->group(function () {
            Route::get('/', [PurchasePayableController::class, 'index']);
            Route::post('/{purchase}/payments', [PurchasePayableController::class, 'storePayment'])->name('.payment');
        });
    });

    // Pengaturan Sistem Tertinggi (HANYA Master)
    Route::middleware('can:manage-system')->group(function () {
        Route::get('/preview', [PreviewController::class, 'index'])->name('preview');
    });

    Route::prefix('backoffice')->name('backoffice.')->group(function () {
        // Products (Katalog Produk)
        Route::get('products/check-duplicate', [ProductController::class, 'checkDuplicate'])->name('products.check-duplicate');
        Route::resource('products', ProductController::class)->except(['show']);

        // Customers (Pelanggan & Multi-Price)
        Route::resource('customers', CustomerController::class);

        // Sales Returns (Retur Penjualan Pelanggan - kasir / counter)
        Route::resource('sales-returns', SalesReturnController::class);

        // Operasional Gudang & Cabang (Master & Branch Admin)
        Route::middleware('can:access-inventory')->group(function () {
            Route::resource('warehouses', WarehouseController::class)->except(['show']);
        });

        // Operasional Keuangan Cabang & Pembelian Cabang (Master & Branch Admin)
        Route::middleware('can:manage-branch-operations')->group(function () {
            // Categories
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Users & Cashiers
            Route::resource('users', UserController::class)->except(['show']);

            // Suppliers (Buku Pemasok + Bayar Hutang FIFO)
            Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'storePayment'])->name('suppliers.payments.store');
            Route::resource('suppliers', SupplierController::class);

            // Purchase Orders & Returns
            Route::resource('purchase-orders', PurchaseOrderController::class);
            Route::resource('purchase-returns', PurchaseReturnController::class);

            // Dasbor Akuntansi (4 pilar: kas, jurnal, pengeluaran, laporan)
            Route::get('finance', [FinanceDashboardController::class, 'index'])->name('finance.dashboard');
            Route::post('finance/journals', [FinanceDashboardController::class, 'storeJournal'])->name('finance.journals.store');

            // Expense Categories & Expenses (Biaya Operasional Cabang)
            Route::resource('expense-categories', ExpenseCategoryController::class);
            Route::resource('expenses', ExpenseController::class);

            // Cash Transfers & Transactions (Mutasi Kas Cabang)
            Route::resource('cash-transfers', CashTransferController::class);
            Route::resource('cash-transactions', CashTransactionController::class);

            // Opening Balances (Setup Saldo Awal Cabang)
            Route::get('opening-balances', [OpeningBalanceController::class, 'create'])->name('opening-balances.create');
            Route::post('opening-balances', [OpeningBalanceController::class, 'store'])->name('opening-balances.store');

            // Fixed Assets (Harta Tetap Cabang)
            Route::post('fixed-assets/run-depreciation', [FixedAssetController::class, 'runDepreciation'])->name('fixed-assets.run-depreciation');
            Route::resource('fixed-assets', FixedAssetController::class);

            Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
            Route::post('assistant/ask', [AssistantController::class, 'ask'])->name('assistant.ask');

            // Payments (Pembayaran Piutang & Hutang Operasional)
            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/receivables/create', [PaymentController::class, 'createAR'])->name('payments.receivables.create');
            Route::post('payments/receivables', [PaymentController::class, 'storeAR'])->name('payments.receivables.store');
            Route::get('payments/payables/create', [PaymentController::class, 'createAP'])->name('payments.payables.create');
            Route::post('payments/payables', [PaymentController::class, 'storeAP'])->name('payments.payables.store');
        });

        // Pesan Internal antar cabang & Pusat (Master & Branch Admin bercabang)
        Route::middleware('can:use-internal-messages')->prefix('messages')->name('messages.')->group(function () {
            Route::get('/', [InternalMessageController::class, 'index'])->name('index');
            Route::get('fetch', [InternalMessageController::class, 'fetchMessages'])->middleware('throttle:60,1')->name('fetch');
            Route::post('send', [InternalMessageController::class, 'sendMessage'])->middleware('throttle:30,1')->name('send');
        });

        // Modul Enterprise (HANYA Master)
        Route::middleware('can:view-accounting')->group(function () {
            Route::resource('supplier-payments', SupplierPaymentController::class);
        });

        // Pengaturan Sistem (HANYA Master)
        Route::middleware('can:manage-system')->group(function () {
            Route::resource('branches', BranchController::class)->except(['show']);
            Route::post('backup/generate', [BackupController::class, 'generate'])->name('backup.generate');
            Route::get('backup/download', [BackupController::class, 'download'])->name('backup.download');
        });
    });

    Route::get('/backoffice/stock-transfers/{reference}/print', [StockTransferPrintController::class, 'print'])->name('stock-transfers.print');

    // Akses per cabang dicek di dalam TransactionViewerController.
    Route::get('/backoffice/transactions/{reference}/details', [TransactionViewerController::class, 'show'])->name('transactions.details');

    // Pusat Laporan. Alias /reports/sales & /reports/ar-aging dipertahankan untuk tautan lama.
    Route::middleware('can:manage-branch-operations')->group(function () {
        Route::get('/backoffice/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/backoffice/reports/fixed-assets', [ReportController::class, 'fixedAssets'])->name('reports.fixed-assets');
        Route::get('/backoffice/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/backoffice/reports/ar-aging', [ReportController::class, 'arAging'])->name('reports.ar-aging');
        Route::get('/reports/ar-aging', [ReportController::class, 'arAging']);
    });

    Route::middleware('can:view-accounting')->group(function () {
        Route::get('/backoffice/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
        Route::get('/backoffice/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('/backoffice/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('/backoffice/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');

        Route::prefix('/backoffice/reports')->group(function () {
            Route::get('/purchases', [ReportController::class, 'purchases'])->name('reports.purchases');
            Route::get('/inventory/stock-card', [ReportController::class, 'stockCard'])->name('reports.stock-card');
            Route::get('/ap-aging', [ReportController::class, 'apAging'])->name('reports.ap-aging');
        });
    });
});
