<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
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
    ];

    /**
     * @param  array{start_date?:string,end_date?:string,type?:string,branch_id?:int|string|null}  $filters
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

        $rows = $rows
            ->sortByDesc(fn (array $row) => $row['sort_at'])
            ->values();

        $totalInflow = (float) $rows->where('direction', 'in')->sum('amount');
        $totalOutflow = (float) $rows->where('direction', 'out')->sum('amount');

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
            ];
        });
    }

    private function constrainBranch(mixed $query, ?int $branchId): void
    {
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
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
