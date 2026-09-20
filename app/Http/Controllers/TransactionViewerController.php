<?php

namespace App\Http\Controllers;

use App\Models\CashTransfer;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\JournalHeader;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionViewerController extends Controller
{
    /**
     * Tampilkan rincian transaksi spesifik dalam bentuk HTML partial untuk modal.
     */
    public function show(string $reference, Request $request): View
    {
        $ref = trim($reference);

        // 1. Penjualan POS (INV- / POS-)
        $sale = Sale::withoutGlobalScopes()
            ->where('receipt_number', $ref)
            ->with(['items.product', 'branch', 'user', 'cashRegisterShift'])
            ->first();

        if ($sale) {
            return view('backoffice.transactions.partials.sale', compact('sale'));
        }

        // 2. Transfer Stok Antar Gudang / Cabang (ST- / TRF- pada tabel stock_transfers)
        $stockTransfer = StockTransfer::where('reference_number', $ref)
            ->with(['items.sourceProduct', 'items.destinationProduct', 'sourceBranch', 'destinationBranch', 'user'])
            ->first();

        if ($stockTransfer) {
            return view('backoffice.transactions.partials.stock-transfer', compact('stockTransfer'));
        }

        // 3. Pengeluaran Biaya Operasional (EXP-)
        $expense = Expense::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['expenseCategory.chartOfAccount', 'account', 'branch', 'journalHeader.journalLines'])
            ->first();

        if ($expense) {
            return view('backoffice.transactions.partials.expense', compact('expense'));
        }

        // 4. Mutasi Antar Kas & Bank (TRF- pada tabel cash_transfers)
        $cashTransfer = CashTransfer::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['fromAccount', 'toAccount', 'branch', 'journalHeader.journalLines'])
            ->first();

        if ($cashTransfer) {
            return view('backoffice.transactions.partials.cash-transfer', compact('cashTransfer'));
        }

        // 5. Penerimaan Barang / Goods Receipt (GR-)
        $goodsReceipt = GoodsReceipt::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'branch', 'purchaseOrder.supplier'])
            ->first();

        if ($goodsReceipt) {
            return view('backoffice.transactions.partials.goods-receipt', compact('goodsReceipt'));
        }

        // 6. Pembayaran Supplier (PAY-)
        $supplierPayment = SupplierPayment::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['supplier', 'chartOfAccount', 'branch', 'journalHeader'])
            ->first();

        if ($supplierPayment) {
            return view('backoffice.transactions.partials.supplier-payment', compact('supplierPayment'));
        }

        // 7. Penyesuaian Stok / Opname (ADJ-)
        $stockAdjustment = StockAdjustment::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'branch', 'journalHeader'])
            ->first();

        if ($stockAdjustment) {
            return view('backoffice.transactions.partials.stock-adjustment', compact('stockAdjustment'));
        }

        // 8. Retur Pembelian (PRT-)
        $purchaseReturn = PurchaseReturn::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'supplier', 'branch', 'goodsReceipt', 'journalHeader.journalLines.chartOfAccount'])
            ->first();

        if ($purchaseReturn) {
            return view('backoffice.transactions.partials.purchase-return', compact('purchaseReturn'));
        }

        // 9. Fallback: Jurnal Akuntansi Umum / Saldo Awal (OB-)
        $journal = JournalHeader::with(['journalLines.chartOfAccount', 'branch', 'user'])
            ->where('reference_number', $ref)
            ->first();

        if ($journal) {
            return view('backoffice.transactions.partials.journal', compact('journal'));
        }

        // 10. Jika tidak ditemukan
        return view('backoffice.transactions.partials.not-found', ['reference' => $ref]);
    }
}
