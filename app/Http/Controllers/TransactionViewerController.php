<?php

namespace App\Http\Controllers;

use App\Models\CashTransfer;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\JournalHeader;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionViewerController extends Controller
{
    /**
     * Tampilkan rincian transaksi spesifik dalam bentuk HTML partial untuk modal.
     * Non-master hanya boleh melihat transaksi cabangnya sendiri; selain itu dianggap tidak ditemukan.
     */
    public function show(string $reference, Request $request): View|JsonResponse
    {
        $ref = trim($reference);
        $user = $request->user();

        // 1. Penjualan POS (INV- / POS-)
        $sale = Sale::withoutGlobalScopes()
            ->where('receipt_number', $ref)
            ->with(['items.product', 'branch', 'user', 'cashRegisterShift'])
            ->first();

        if ($sale) {
            if (! $this->canView($user, [$sale->branch_id])) {
                return $this->notFound($ref);
            }

            if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'type' => 'sale',
                    'data' => $sale,
                ]);
            }

            return view('backoffice.transactions.partials.sale', compact('sale'));
        }

        // 2. Transfer Stok Antar Gudang / Cabang (ST- / TRF- pada tabel stock_transfers)
        $stockTransfer = StockTransfer::where('reference_number', $ref)
            ->with(['items.sourceProduct', 'items.destinationProduct', 'sourceBranch', 'destinationBranch', 'user'])
            ->first();

        if ($stockTransfer) {
            return $this->canView($user, [$stockTransfer->source_branch_id, $stockTransfer->destination_branch_id])
                ? view('backoffice.transactions.partials.stock-transfer', compact('stockTransfer'))
                : $this->notFound($ref);
        }

        // 3. Pengeluaran Biaya Operasional (EXP-)
        $expense = Expense::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['expenseCategory.chartOfAccount', 'account', 'branch', 'journalHeader.journalLines'])
            ->first();

        if ($expense) {
            return $this->canView($user, [$expense->branch_id])
                ? view('backoffice.transactions.partials.expense', compact('expense'))
                : $this->notFound($ref);
        }

        // 4. Mutasi Antar Kas & Bank (TRF- pada tabel cash_transfers)
        $cashTransfer = CashTransfer::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['fromAccount', 'toAccount', 'branch', 'journalHeader.journalLines'])
            ->first();

        if ($cashTransfer) {
            return $this->canView($user, [$cashTransfer->branch_id])
                ? view('backoffice.transactions.partials.cash-transfer', compact('cashTransfer'))
                : $this->notFound($ref);
        }

        // 5. Penerimaan Barang / Goods Receipt (GR-)
        $goodsReceipt = GoodsReceipt::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'branch', 'purchaseOrder.supplier'])
            ->first();

        if ($goodsReceipt) {
            return $this->canView($user, [$goodsReceipt->branch_id])
                ? view('backoffice.transactions.partials.goods-receipt', compact('goodsReceipt'))
                : $this->notFound($ref);
        }

        // 6. Pembayaran Supplier (PAY-)
        $supplierPayment = SupplierPayment::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['supplier', 'chartOfAccount', 'branch', 'journalHeader'])
            ->first();

        if ($supplierPayment) {
            return $this->canView($user, [$supplierPayment->branch_id])
                ? view('backoffice.transactions.partials.supplier-payment', compact('supplierPayment'))
                : $this->notFound($ref);
        }

        // 7. Penyesuaian Stok / Opname (ADJ-)
        $stockAdjustment = StockAdjustment::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'branch', 'journalHeader'])
            ->first();

        if ($stockAdjustment) {
            return $this->canView($user, [$stockAdjustment->branch_id])
                ? view('backoffice.transactions.partials.stock-adjustment', compact('stockAdjustment'))
                : $this->notFound($ref);
        }

        // 8. Retur Pembelian (PRT-)
        $purchaseReturn = PurchaseReturn::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'supplier', 'branch', 'goodsReceipt', 'journalHeader.journalLines.chartOfAccount'])
            ->first();

        if ($purchaseReturn) {
            return $this->canView($user, [$purchaseReturn->branch_id])
                ? view('backoffice.transactions.partials.purchase-return', compact('purchaseReturn'))
                : $this->notFound($ref);
        }

        // 9. Retur Penjualan (SR-)
        $salesReturn = SalesReturn::withoutGlobalScopes()
            ->where('reference_number', $ref)
            ->with(['items.product', 'user', 'branch', 'sale', 'chartOfAccount', 'cashRegisterShift', 'journalHeader.journalLines.chartOfAccount'])
            ->first();

        if ($salesReturn) {
            return $this->canView($user, [$salesReturn->branch_id])
                ? view('backoffice.transactions.partials.sales-return', compact('salesReturn'))
                : $this->notFound($ref);
        }

        // 10. Fallback: Jurnal Akuntansi Umum / Saldo Awal (OB-)
        $journal = JournalHeader::with(['journalLines.chartOfAccount', 'branch', 'user'])
            ->where('reference_number', $ref)
            ->first();

        if ($journal) {
            return $this->canView($user, [$journal->branch_id])
                ? view('backoffice.transactions.partials.journal', compact('journal'))
                : $this->notFound($ref);
        }

        // 11. Jika tidak ditemukan
        return $this->notFound($ref);
    }

    /**
     * @param  array<int, int|string|null>  $branchIds
     */
    private function canView(?User $user, array $branchIds): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        $allowed = array_map('intval', array_filter($branchIds, fn ($id) => $id !== null));

        return $user->branch_id !== null && in_array((int) $user->branch_id, $allowed, true);
    }

    private function notFound(string $reference): View
    {
        return view('backoffice.transactions.partials.not-found', ['reference' => $reference]);
    }
}
