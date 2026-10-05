<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchasePaymentController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\TransactionViewerController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\PurchaseReturnController;
use App\Http\Controllers\Web\SalesReturnController;
use App\Http\Controllers\Web\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user()->load('branch');
    });

    // Products CRUD (with branch isolation via HasBranchScope)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/barcode/{barcode}', [ProductController::class, 'byBarcode']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    // Categories CRUD (global resource, superadmin only for write operations)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Branches CRUD (superadmin only)
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::get('/branches/{branch}', [BranchController::class, 'show']);
    Route::put('/branches/{branch}', [BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

    // Inter-branch stock distribution
    Route::get('/stock-transfers', [StockTransferController::class, 'index']);
    Route::post('/stock-transfers', [StockTransferController::class, 'store']);
    Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/sales', [CheckoutController::class, 'store']);

    // Master / Branch Operations
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);

    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index']);
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store']);
    Route::get('/goods-receipts/{id}', [GoodsReceiptController::class, 'show']);

    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);

    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index']);
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store']);

    Route::post('/purchase-returns', [PurchaseReturnController::class, 'store']);
    Route::post('/sales-returns', [SalesReturnController::class, 'store']);
    Route::post('/payments/ap', [PaymentController::class, 'storeAP']);
    Route::post('/payments/ar', [PaymentController::class, 'storeAR']);

    // Report Center & Transaction Viewer (JSON API)
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('/transactions/{reference}/details', [TransactionViewerController::class, 'show']);

    // Enterprise Accounting & AP (Strictly Master only)
    Route::middleware('can:view-accounting')->group(function () {
        Route::get('/journals', [JournalController::class, 'index']);
        Route::post('/journals', [JournalController::class, 'store']);

        // Purchases & Flexible Accounts Payable (Distributor)
        Route::get('/purchases', [PurchaseController::class, 'index']);
        Route::post('/purchases', [PurchaseController::class, 'store']);
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
        Route::get('/purchase-payments', [PurchasePaymentController::class, 'index']);
        Route::post('/purchase-payments', [PurchasePaymentController::class, 'store']);
        Route::post('/purchases/{purchase}/payments', [PurchasePaymentController::class, 'store']);
    });
});
