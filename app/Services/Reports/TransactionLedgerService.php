<?php

namespace App\Services\Reports;

use App\Enums\CashFlowTag;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\JournalHeader;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        'cash_journal' => 'Kas Masuk/Keluar Lainnya',
    ];

    private const NON_CASH_DEFAULT_NOTE = 'Tidak ada uang kas/bank yang berpindah';

    /**
     * @param  array{start_date?:string,end_date?:string,type?:string,branch_id?:int|string|null,search?:string|null}  $filters
     * @return array{
     *     rows: Collection,
     *     total_inflow: float,
     *     total_outflow: float,
     *     net: float,
     *     opening_balance: float,
     *     closing_balance: float,
     *     is_filtered: bool,
     *     unjournaled_count: int,
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

        // Semua sumber selalu dibaca agar saldo berjalan tetap utuh; filter jenis dan
        // pencarian hanya menyaring baris yang ditampilkan.
        $documentRows = collect()
            ->concat($this->saleRows($start, $end, $branchId))
            ->concat($this->expenseRows($start, $end, $branchId))
            ->concat($this->salesReturnRows($start, $end, $branchId))
            ->concat($this->purchaseReturnRows($start, $end, $branchId))
            ->concat($this->paymentRows($start, $end, $branchId))
            ->concat($this->supplierPaymentRows($start, $end, $branchId))
            ->concat($this->purchasePaymentRows($start, $end, $branchId))
            ->concat($this->goodsReceiptRows($start, $end, $branchId))
            ->concat($this->cashTransferRows($start, $end, $branchId))
            ->concat($this->stockAdjustmentRows($start, $end, $branchId));

        $cashAccountIds = $this->cashAccountIds();
        $journals = $this->periodJournals($start, $end, $branchId, $cashAccountIds);
        $allRows = $this->applyJournalCash($documentRows, $journals);

        $openingBalance = $this->openingCashBalance($start, $branchId, $cashAccountIds);
        $runningBalance = $openingBalance;
        $sequence = 0;

        $allRows = $allRows
            ->sortBy([
                fn (array $a, array $b) => strcmp($a['sort_at'], $b['sort_at']),
                fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order'],
                fn (array $a, array $b) => strcmp((string) $a['reference'], (string) $b['reference']),
            ])
            ->values()
            ->map(function (array $row) use (&$runningBalance, &$sequence) {
                $runningBalance = round($runningBalance + $row['cash_in'] - $row['cash_out'], 2);
                $row['sequence'] = ++$sequence;
                $row['balance'] = $runningBalance;

                return $row;
            });

        $totalPeriodInflow = round((float) $allRows->sum('cash_in'), 2);
        $totalPeriodOutflow = round((float) $allRows->sum('cash_out'), 2);

        $rows = $type === 'all' ? $allRows : $allRows->where('type', $type)->values();

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) $row['reference']), $needle)
                || str_contains(mb_strtolower($row['description']), $needle))
                ->values();
        }

        $totalInflow = round((float) $rows->sum('cash_in'), 2);
        $totalOutflow = round((float) $rows->sum('cash_out'), 2);

        return [
            'rows' => $rows,
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'net' => round($totalInflow - $totalOutflow, 2),
            'opening_balance' => $openingBalance,
            'closing_balance' => round($openingBalance + $totalPeriodInflow - $totalPeriodOutflow, 2),
            'is_filtered' => $type !== 'all' || $search !== '',
            'unjournaled_count' => $rows->where('is_journaled', false)->count(),
            'count' => $rows->count(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'type' => $type,
            'branch_id' => $branchId,
            'search' => $search,
            'is_master' => $isMaster,
        ];
    }

    /**
     * Uang nyata diambil dari jurnal kas/bank dengan nomor referensi yang sama. Dokumen
     * tanpa jurnal memakai perkiraan dari dokumennya, dan jurnal kas tanpa dokumen
     * (kas masuk/keluar manual, saldo awal, koreksi) tampil sebagai baris tersendiri.
     *
     * @param  Collection<int,array<string,mixed>>  $documentRows
     * @param  Collection<int,array<string,mixed>>  $journals
     * @return Collection<int,array<string,mixed>>
     */
    private function applyJournalCash(Collection $documentRows, Collection $journals): Collection
    {
        $journalsByReference = $journals
            ->filter(fn (array $journal) => $journal['reference'] !== '')
            ->groupBy('reference');
        $claimedReferences = [];

        $rows = $documentRows->map(function (array $row) use ($journalsByReference, &$claimedReferences) {
            $reference = (string) ($row['reference'] ?? '');
            $matches = $reference !== '' && ! isset($claimedReferences[$reference])
                ? $journalsByReference->get($reference)
                : null;

            if ($matches !== null) {
                $claimedReferences[$reference] = true;

                return $this->withCash($row, $matches->sum('cash_debit') - $matches->sum('cash_credit'), true);
            }

            $estimatedNet = match (true) {
                ! $row['is_cash'] => 0.0,
                $row['direction'] === 'in' => (float) $row['amount'],
                $row['direction'] === 'out' => -(float) $row['amount'],
                default => 0.0,
            };

            return $this->withCash($row, $estimatedNet, false);
        });

        $journalRows = $journals
            ->reject(fn (array $journal) => isset($claimedReferences[$journal['reference']]))
            ->filter(fn (array $journal) => $journal['cash_debit'] > 0 || $journal['cash_credit'] > 0)
            ->map(fn (array $journal) => $this->withCash([
                'sort_at' => $journal['sort_at'],
                'date' => $journal['date'],
                'reference' => $journal['reference'] !== '' ? $journal['reference'] : '-',
                'type' => 'cash_journal',
                'type_label' => self::TYPES['cash_journal'],
                'description' => $journal['description'] !== '' ? $journal['description'] : 'Jurnal Kas/Bank',
                'branch_name' => $journal['branch_name'],
                'amount' => max($journal['cash_debit'], $journal['cash_credit']),
                'direction' => 'neutral',
                'is_cash' => true,
                'non_cash_note' => null,
            ], $journal['cash_debit'] - $journal['cash_credit'], true));

        return $rows->concat($journalRows)->values();
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function withCash(array $row, float $net, bool $isJournaled): array
    {
        $net = round($net, 2);
        $hasCash = abs($net) >= 0.01;

        $row['cash_in'] = $net > 0 ? $net : 0.0;
        $row['cash_out'] = $net < 0 ? -$net : 0.0;
        $row['is_cash'] = $hasCash;
        $row['is_journaled'] = $isJournaled;
        $row['sort_order'] = array_search($row['type'], array_keys(self::TYPES), true) ?: 0;

        if ($hasCash) {
            $row['direction'] = $net > 0 ? 'in' : 'out';
            $row['non_cash_note'] = null;
        } else {
            $row['non_cash_note'] = $row['non_cash_note'] ?? self::NON_CASH_DEFAULT_NOTE;
        }

        return $row;
    }

    /**
     * @param  array<int,int>  $cashAccountIds
     * @return Collection<int,array{reference:string,sort_at:string,date:mixed,description:string,branch_name:string,cash_debit:float,cash_credit:float}>
     */
    private function periodJournals(Carbon $start, Carbon $end, ?int $branchId, array $cashAccountIds): Collection
    {
        $headers = JournalHeader::query()
            ->with('branch:id,name')
            ->whereDate('transaction_date', '>=', $start->toDateString())
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get(['id', 'branch_id', 'transaction_date', 'reference_number', 'description', 'created_at']);

        $cashSums = $this->cashLineQuery($branchId, $cashAccountIds)
            ->whereDate('journal_headers.transaction_date', '>=', $start->toDateString())
            ->whereDate('journal_headers.transaction_date', '<=', $end->toDateString())
            ->groupBy('journal_lines.journal_header_id')
            ->selectRaw('journal_lines.journal_header_id, COALESCE(SUM(journal_lines.debit), 0) as cash_debit, COALESCE(SUM(journal_lines.credit), 0) as cash_credit')
            ->get()
            ->keyBy('journal_header_id');

        return $headers->map(function (JournalHeader $header) use ($cashSums) {
            $sums = $cashSums->get($header->id);

            return [
                'reference' => trim((string) $header->reference_number),
                'sort_at' => ($header->transaction_date?->toDateString() ?? '').' '.$header->created_at?->toTimeString(),
                'date' => $header->transaction_date,
                'description' => trim((string) $header->description),
                'branch_name' => $header->branch?->name ?? 'Pusat',
                'cash_debit' => round((float) ($sums->cash_debit ?? 0), 2),
                'cash_credit' => round((float) ($sums->cash_credit ?? 0), 2),
            ];
        });
    }

    /**
     * @param  array<int,int>  $cashAccountIds
     */
    private function openingCashBalance(Carbon $start, ?int $branchId, array $cashAccountIds): float
    {
        $totals = $this->cashLineQuery($branchId, $cashAccountIds)
            ->whereDate('journal_headers.transaction_date', '<', $start->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as total_debit, COALESCE(SUM(journal_lines.credit), 0) as total_credit')
            ->first();

        return round((float) ($totals->total_debit ?? 0) - (float) ($totals->total_credit ?? 0), 2);
    }

    /**
     * @param  array<int,int>  $cashAccountIds
     */
    private function cashLineQuery(?int $branchId, array $cashAccountIds): Builder
    {
        return JournalLine::query()
            ->join('journal_headers', 'journal_lines.journal_header_id', '=', 'journal_headers.id')
            ->whereNull('journal_headers.deleted_at')
            ->whereIn('journal_lines.chart_of_account_id', $cashAccountIds)
            ->when($branchId, fn ($query) => $query->where('journal_headers.branch_id', $branchId));
    }

    /**
     * Kas (1110), Bank (1120), sub-akunnya, dan akun laci kasir yang bertanda kas.
     *
     * @return array<int,int>
     */
    private function cashAccountIds(): array
    {
        return ChartOfAccount::withTrashed()
            ->where('type', 'asset')
            ->where(fn ($query) => $query
                ->whereIn('code', ChartOfAccount::CASH_BANK_CODES)
                ->orWhere('code', 'like', '1110%')
                ->orWhere('code', 'like', '1120%')
                ->orWhere('cash_flow_tag', CashFlowTag::Cash->value))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    private function purchasePaymentRows(Carbon $start, Carbon $end, ?int $branchId): Collection
    {
        $query = PurchasePayment::withoutGlobalScopes()
            ->with([
                'purchase' => fn ($q) => $q->withoutGlobalScopes(),
                'purchase.distributor' => fn ($q) => $q->withoutGlobalScopes(),
                'branch',
            ])
            ->whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString());

        $this->constrainBranch($query, $branchId);

        return $query->get()->map(function (PurchasePayment $payment) {
            $distributor = $payment->purchase?->distributor?->name ?: 'Distributor';
            $invoice = $payment->purchase?->invoice_number;

            return [
                'sort_at' => ($payment->payment_date?->toDateString() ?? '').' '.$payment->created_at?->toTimeString(),
                'date' => $payment->payment_date,
                'reference' => $payment->reference_number,
                'type' => 'payment',
                'type_label' => 'Pembayaran Distributor',
                'description' => "Pembayaran Hutang - {$distributor}".($invoice ? " (Faktur {$invoice})" : ''),
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
