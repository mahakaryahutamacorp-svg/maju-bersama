<?php

namespace App\Services\Reports;

use App\Models\CashTransfer;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TransactionLedgerService
{
    public const TYPES = [
        'all' => 'Semua Transaksi',
        'sale' => 'Penjualan POS',
        'expense' => 'Pengeluaran',
        'sales_return' => 'Retur Penjualan',
        'purchase_return' => 'Retur Pembelian',
        'payment' => 'Pembayaran (Piutang/Hutang)',
        'goods_receipt' => 'Terima Barang',
        'cash_transfer' => 'Pindah Kas/Bank',
        'stock_adjustment' => 'Stok Opname',
    ];

    /**
     * @param  array{start_date?:string,end_date?:string,type?:string,branch_id?:int|string|null,search?:string|null}  $filters
     * @return array{
     *     rows: Collection,
     *     total_inflow: float,
     *     total_outflow: float,
     *     net: float,
     *     count: int,
     *     start_date: string,
     *     end_date: string,
     *     type: string,
     *     branch_id: int|null,
     *     search: string,
     *     is_master: bool
     * }
     */
    public function getLedger(User $actor, array $filters): array
    {
        $isMaster = $actor->isMaster();
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? now()->toDateString();
        $type = $filters['type'] ?? 'all';

        if (! array_key_exists($type, self::TYPES)) {
            $type = 'all';
        }

        $rawBranchId = $filters['branch_id'] ?? null;
        $branchId = $isMaster
            ? ($rawBranchId !== null && $rawBranchId !== '' ? (int) $rawBranchId : null)
            : (int) $actor->branch_id;

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $rows = collect();

        if (in_array($type, ['all', 'sale'], true)) {
            $rows = $rows->concat($this->saleRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'expense'], true)) {
            $rows = $rows->concat($this->expenseRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'sales_return'], true)) {
            $rows = $rows->concat($this->salesReturnRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'purchase_return'], true)) {
            $rows = $rows->concat($this->purchaseReturnRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'payment'], true)) {
            $rows = $rows->concat($this->paymentRows($start, $end, $branchId));
            $rows = $rows->concat($this->supplierPaymentRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'goods_receipt'], true)) {
            $rows = $rows->concat($this->goodsReceiptRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'cash_transfer'], true)) {
            $rows = $rows->concat($this->cashTransferRows($start, $end, $branchId));
        }

        if (in_array($type, ['all', 'stock_adjustment'], true)) {
            $rows = $rows->concat($this->stockAdjustmentRows($start, $end, $branchId));
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) $row['reference']), $needle)
                || str_contains(mb_strtolower($row['description']), $needle));
        }

        $rows = $rows
            ->sortByDesc(fn (array $row) => $row['sort_at'])
            ->values();

        $cashRows = $rows->where('is_cash', true);
        $totalInflow = (float) $cashRows->where('direction', 'in')->sum('amount');
        $totalOutflow = (float) $cashRows->where('direction', 'out')->sum('amount');

        return [
            'rows' => $rows,
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'net' => $totalInflow - $totalOutflow,
            'count' => $rows->count(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'type' => $type,
            'branch_id' => $branchId,
            'search' => $search,
            'is_master' => $isMaster,
        ];
    }

    public function describeSale(Sale $sale): string
    {
        $method = $this->saleMethodLabel($sale->payment_method);
        $customer = $sale->customer?->name ?: 'Pelanggan Umum';

        return "Penjualan {$method} - {$customer}";
    }

    private function saleRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = Sale::withoutGlobalScopes()
            ->with(['customer', 'branch'])
            ->whereBetween('created_at', [$start, $end]);

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (Sale $sale) {
            $isCredit = $this->isCreditMethod($sale->payment_method);

            return [
                'sort_at' => $sale->created_at?->toDateTimeString() ?? '',
                'date' => $sale->created_at,
                'reference' => $sale->receipt_number,
                'type' => 'sale',
                'type_label' => 'Penjualan POS',
                'description' => $this->describeSale($sale),
                'branch_name' => $sale->branch?->name ?? '-',
                'amount' => (float) $sale->total_amount,
                'direction' => 'in',
                'is_cash' => ! $isCredit,
                'non_cash_note' => $isCredit ? 'Piutang, uang belum diterima' : null,
            ];
        });
    }

    private function expenseRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = Expense::withoutGlobalScopes()
            ->with(['expenseCategory' => fn ($q) => $q->withoutGlobalScopes(), 'branch'])
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (Expense $expense) {
            $category = $expense->expenseCategory?->name ?: 'Biaya Operasional';
            $note = trim((string) $expense->notes);
            $detail = $note !== '' ? "{$category} - {$note}" : $category;

            return [
                'sort_at' => ($expense->expense_date?->toDateString() ?? '').' '.$expense->created_at?->toTimeString(),
                'date' => $expense->expense_date,
                'reference' => $expense->reference_number,
                'type' => 'expense',
                'type_label' => 'Pengeluaran',
                'description' => "Pengeluaran: {$detail}",
                'branch_name' => $expense->branch?->name ?? '-',
                'amount' => (float) $expense->amount,
                'direction' => 'out',
                'is_cash' => true,
                'non_cash_note' => null,
            ];
        });
    }

    private function salesReturnRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = SalesReturn::withoutGlobalScopes()
            ->with(['branch'])
            ->whereDate('return_date', '>=', $start->toDateString())
            ->whereDate('return_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (SalesReturn $salesReturn) {
            $customer = $salesReturn->customer_name ?: 'Pelanggan Umum';
            $cutsReceivable = $this->isCreditMethod($salesReturn->refund_method);

            return [
                'sort_at' => ($salesReturn->return_date?->toDateString() ?? '').' '.$salesReturn->created_at?->toTimeString(),
                'date' => $salesReturn->return_date,
                'reference' => $salesReturn->reference_number,
                'type' => 'sales_return',
                'type_label' => 'Retur Penjualan',
                'description' => "Retur Penjualan - {$customer}",
                'branch_name' => $salesReturn->branch?->name ?? '-',
                'amount' => (float) $salesReturn->total_amount,
                'direction' => 'out',
                'is_cash' => ! $cutsReceivable,
                'non_cash_note' => $cutsReceivable ? 'Memotong piutang, tanpa uang keluar' : null,
            ];
        });
    }

    private function purchaseReturnRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = PurchaseReturn::withoutGlobalScopes()
            ->with(['supplier' => fn ($q) => $q->withoutGlobalScopes(), 'branch'])
            ->whereDate('return_date', '>=', $start->toDateString())
            ->whereDate('return_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (PurchaseReturn $purchaseReturn) {
            $supplier = $purchaseReturn->supplier?->name ?: 'Supplier';

            return [
                'sort_at' => ($purchaseReturn->return_date?->toDateString() ?? '').' '.$purchaseReturn->created_at?->toTimeString(),
                'date' => $purchaseReturn->return_date,
                'reference' => $purchaseReturn->reference_number,
                'type' => 'purchase_return',
                'type_label' => 'Retur Pembelian',
                'description' => "Retur Pembelian - {$supplier}",
                'branch_name' => $purchaseReturn->branch?->name ?? '-',
                'amount' => (float) $purchaseReturn->total_amount,
                'direction' => 'in',
                'is_cash' => false,
                'non_cash_note' => 'Memotong hutang ke pemasok, tanpa uang masuk',
            ];
        });
    }

    private function paymentRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = Payment::withoutGlobalScopes()
            ->with([
                'customer' => fn ($q) => $q->withoutGlobalScopes(),
                'supplier' => fn ($q) => $q->withoutGlobalScopes(),
                'branch',
                'allocations.sale.customer',
            ])
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (Payment $payment) {
            $isAr = strtoupper((string) $payment->type) === 'AR';
            $party = $isAr
                ? ($payment->customer?->name
                    ?? $payment->allocations->first()?->sale?->customer?->name
                    ?? 'Pelanggan Umum')
                : ($payment->supplier?->name ?: 'Supplier');

            return [
                'sort_at' => ($payment->payment_date?->toDateString() ?? '').' '.$payment->created_at?->toTimeString(),
                'date' => $payment->payment_date,
                'reference' => $payment->reference_number,
                'type' => 'payment',
                'type_label' => $isAr ? 'Pembayaran Piutang' : 'Pembayaran Hutang',
                'description' => $isAr
                    ? "Pembayaran Piutang - {$party}"
                    : "Pembayaran Hutang - {$party}",
                'branch_name' => $payment->branch?->name ?? '-',
                'amount' => (float) $payment->amount,
                'direction' => $isAr ? 'in' : 'out',
                'is_cash' => true,
                'non_cash_note' => null,
            ];
        });
    }

    private function supplierPaymentRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = SupplierPayment::withoutGlobalScopes()
            ->with(['supplier' => fn ($q) => $q->withoutGlobalScopes(), 'branch'])
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (SupplierPayment $payment) {
            $supplier = $payment->supplier?->name ?: 'Supplier';

            return [
                'sort_at' => ($payment->payment_date?->toDateString() ?? '').' '.$payment->created_at?->toTimeString(),
                'date' => $payment->payment_date,
                'reference' => $payment->reference_number,
                'type' => 'payment',
                'type_label' => 'Pembayaran Supplier',
                'description' => "Pembayaran Hutang - {$supplier}",
                'branch_name' => $payment->branch?->name ?? '-',
                'amount' => (float) $payment->amount,
                'direction' => 'out',
                'is_cash' => true,
                'non_cash_note' => null,
            ];
        });
    }

    private function goodsReceiptRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = GoodsReceipt::query()
            ->with(['supplier' => fn ($q) => $q->withoutGlobalScopes(), 'branch'])
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (GoodsReceipt $receipt) {
            $supplier = $receipt->supplier?->name ?: ($receipt->supplier_name ?: 'Pemasok');
            $isCredit = $receipt->payment_type === 'credit';

            return [
                'sort_at' => ($receipt->date?->toDateString() ?? '').' '.$receipt->created_at?->toTimeString(),
                'date' => $receipt->date,
                'reference' => $receipt->reference_number,
                'type' => 'goods_receipt',
                'type_label' => 'Terima Barang',
                'description' => ($isCredit ? 'Beli Barang Kredit - ' : 'Beli Barang Tunai - ').$supplier,
                'branch_name' => $receipt->branch?->name ?? '-',
                'amount' => (float) $receipt->total_amount,
                'direction' => 'out',
                'is_cash' => ! $isCredit,
                'non_cash_note' => $isCredit ? 'Jadi hutang, uang keluar saat bayar hutang' : null,
            ];
        });
    }

    private function cashTransferRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = CashTransfer::withoutGlobalScopes()
            ->with(['fromAccount', 'toAccount', 'branch'])
            ->whereDate('transfer_date', '>=', $start->toDateString())
            ->whereDate('transfer_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (CashTransfer $transfer) {
            $from = $transfer->fromAccount?->name ?? 'Kas';
            $to = $transfer->toAccount?->name ?? 'Bank';

            return [
                'sort_at' => ($transfer->transfer_date?->toDateString() ?? '').' '.$transfer->created_at?->toTimeString(),
                'date' => $transfer->transfer_date,
                'reference' => $transfer->reference_number,
                'type' => 'cash_transfer',
                'type_label' => 'Pindah Kas/Bank',
                'description' => "Pindah Uang: {$from} ke {$to}",
                'branch_name' => $transfer->branch?->name ?? '-',
                'amount' => (float) $transfer->amount,
                'direction' => 'neutral',
                'is_cash' => false,
                'non_cash_note' => 'Hanya pindah tempat, total uang tetap',
            ];
        });
    }

    private function stockAdjustmentRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = StockAdjustment::withoutGlobalScopes()
            ->with(['branch'])
            ->where(function ($dateQuery) use ($start, $end) {
                $dateQuery->where(fn ($primary) => $primary
                    ->whereDate('adjustment_date', '>=', $start->toDateString())
                    ->whereDate('adjustment_date', '<=', $end->toDateString()))
                    ->orWhere(fn ($fallback) => $fallback->whereNull('adjustment_date')
                        ->whereDate('date', '>=', $start->toDateString())
                        ->whereDate('date', '<=', $end->toDateString()));
            });

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (StockAdjustment $adjustment) {
            $date = $adjustment->adjustment_date ?? $adjustment->date;
            $difference = (float) $adjustment->total_gain_value - (float) $adjustment->total_loss_value;

            return [
                'sort_at' => ($date?->toDateString() ?? '').' '.$adjustment->created_at?->toTimeString(),
                'date' => $date,
                'reference' => $adjustment->reference_number,
                'type' => 'stock_adjustment',
                'type_label' => 'Stok Opname',
                'description' => $difference < 0 ? 'Stok Opname - Barang Kurang' : 'Stok Opname - Barang Lebih',
                'branch_name' => $adjustment->branch?->name ?? '-',
                'amount' => abs($difference),
                'direction' => $difference < 0 ? 'out' : 'in',
                'is_cash' => false,
                'non_cash_note' => 'Selisih nilai stok, tanpa uang',
            ];
        });
    }

    private function constrainBranch(mixed $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
    }

    private function isCreditMethod(?string $method): bool
    {
        return in_array(strtolower((string) $method), ['tempo', 'piutang', 'kredit', 'credit'], true);
    }

    private function saleMethodLabel(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'tempo', 'piutang', 'kredit' => 'Kredit',
            'transfer', 'bank' => 'Transfer',
            'qris' => 'QRIS',
            default => 'Tunai',
        };
    }
}
