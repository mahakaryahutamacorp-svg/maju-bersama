<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ARAPPaymentService
{
    public const ACCOUNT_RECEIVABLE_CODE = '1130';

    public const ACCOUNT_PAYABLE_CODE = '2110';

    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected ReceivableBalanceService $receivableBalance,
    ) {}

    /**
     * @param  array<int, array{sale_id?: int|string|null, allocated_amount?: float|int|string|null}>  $allocations
     * @return array<int, Sale>
     *
     * @throws ValidationException
     */
    private function assertReceivableAllocations(string $amount, array $allocations, ?User $actor): array
    {
        $requested = [];
        $totalAllocated = '0.00';

        foreach ($allocations as $index => $allocation) {
            $allocated = Money::of($allocation['allocated_amount'] ?? 0);
            if (bccomp($allocated, '0.00', 2) !== 1) {
                continue;
            }

            $saleId = (int) ($allocation['sale_id'] ?? 0);
            if ($saleId <= 0) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.sale_id" => ['Faktur penjualan untuk alokasi ini wajib dipilih.'],
                ]);
            }

            $requested[$saleId] = bcadd($requested[$saleId] ?? '0.00', $allocated, 2);
            $totalAllocated = bcadd($totalAllocated, $allocated, 2);
        }

        if ($requested === []) {
            throw ValidationException::withMessages([
                'allocations' => ['Pilih minimal satu faktur penjualan yang akan dialokasikan pembayarannya.'],
            ]);
        }

        if (bccomp($amount, $totalAllocated, 2) !== 0) {
            throw ValidationException::withMessages([
                'amount' => ['Nominal pembayaran ('.Money::rupiah($amount).') harus sama dengan total alokasi faktur ('.Money::rupiah($totalAllocated).').'],
            ]);
        }

        $sales = Sale::withoutGlobalScopes()
            ->whereIn('id', array_keys($requested))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $returned = $this->receivableBalance->returnedBySale(array_keys($requested));

        foreach ($requested as $saleId => $allocated) {
            $sale = $sales->get($saleId);

            if (! $sale || ($actor instanceof User && ! $actor->isMaster() && (int) $sale->branch_id !== (int) $actor->branch_id)) {
                throw ValidationException::withMessages([
                    'allocations' => ['Faktur penjualan tidak ditemukan di cabang Anda.'],
                ]);
            }

            $outstanding = $this->receivableBalance->outstanding($sale, $returned[$saleId] ?? '0.00');
            if (bccomp($allocated, $outstanding, 2) === 1) {
                throw ValidationException::withMessages([
                    'allocations' => ["Alokasi untuk faktur {$sale->receipt_number} (".Money::rupiah($allocated).') melebihi sisa piutangnya ('.Money::rupiah($outstanding).').'],
                ]);
            }
        }

        return $sales->all();
    }

    /**
     * Memproses penerimaan pembayaran piutang pelanggan (Account Receivable / AR).
     *
     * 1. Simpan rekaman payments (type: 'AR').
     * 2. Simpan setiap alokasi ke payment_allocations.
     * 3. Update paid_amount & payment_status pada tabel sales (PAID jika paid_amount >= total_amount, PARTIAL jika belum).
     * 4. Bukukan jurnal akuntansi:
     *    Debit: Kas/Bank (account_id)
     *    Kredit: Piutang Usaha (1130)
     *
     * @throws ValidationException
     */
    public function processARPayment(array $data, array $allocations, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($data, $allocations, $actor): Payment {
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal pembayaran piutang harus lebih besar dari 0.'],
                ]);
            }

            $lockedSales = $this->assertReceivableAllocations(Money::of($data['amount']), $allocations, $actor);

            $bankAccount = ChartOfAccount::findOrFail($data['account_id']);
            $arAccount = $this->getReceivableAccount();

            $branchId = $data['branch_id']
                ?? $actor?->branch_id
                ?? auth()->user()?->branch_id
                ?? Branch::value('id');

            $paymentDate = $data['payment_date'] ?? now()->toDateString();
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : ('AR-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)));

            $notes = $data['notes'] ?? null;

            // 1. Simpan Header Payment
            $payment = Payment::create([
                'branch_id' => $branchId,
                'type' => 'AR',
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => null,
                'account_id' => $bankAccount->id,
                'payment_date' => $paymentDate,
                'reference_number' => $referenceNumber,
                'amount' => $amount,
                'notes' => $notes,
            ]);

            // 2. Simpan Alokasi dan update tabel Sales
            $totalAllocated = '0.0000';
            foreach ($allocations as $alloc) {
                $saleId = $alloc['sale_id'] ?? null;
                $allocatedAmount = (string) ($alloc['allocated_amount'] ?? 0);

                if (bccomp($allocatedAmount, '0', 4) <= 0) {
                    continue;
                }

                $totalAllocated = bcadd($totalAllocated, $allocatedAmount, 4);

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'sale_id' => $saleId,
                    'purchase_order_id' => null,
                    'allocated_amount' => $allocatedAmount,
                ]);

                if ($saleId) {
                    $sale = $lockedSales[(int) $saleId];
                    $sale->paid_amount = bcadd(Money::of($sale->paid_amount ?? 0), Money::of($allocatedAmount), 2);
                    $this->receivableBalance->refreshPaymentStatus($sale);
                }
            }

            // 3. Jurnal Otomatis Berpasangan:
            // Debit: Kas/Bank (account_id) senilai amount
            // Kredit: Piutang Usaha senilai amount
            $description = "Penerimaan Pembayaran Piutang (AR) Ref: {$referenceNumber} via {$bankAccount->name}";
            if ($notes) {
                $description .= " - {$notes}";
            }

            $journal = $this->journalEntryService->createEntry([
                'branch_id' => $branchId,
                'user_id' => $actor?->id ?? auth()->id(),
                'transaction_date' => $paymentDate,
                'reference_number' => $referenceNumber,
                'description' => $description,
                'lines' => [
                    [
                        'chart_of_account_id' => $bankAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => "Penerimaan dana kas/bank piutang {$referenceNumber}",
                    ],
                    [
                        'chart_of_account_id' => $arAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => "Pengurangan piutang usaha {$referenceNumber}",
                    ],
                ],
            ]);

            $payment->update(['journal_header_id' => $journal->id]);

            return $payment->load(['allocations.sale', 'account', 'journalHeader.journalLines', 'branch']);
        });
    }

    /**
     * Memproses pelunasan pembayaran hutang supplier (Account Payable / AP).
     *
     * 1. Simpan rekaman payments (type: 'AP').
     * 2. Simpan setiap alokasi ke payment_allocations.
     * 3. Update paid_amount & payment_status pada tabel purchase_orders (PAID/PARTIAL).
     * 4. Bukukan jurnal akuntansi:
     *    Debit: Hutang Usaha (2110)
     *    Kredit: Kas/Bank (account_id)
     *
     * @throws ValidationException
     */
    public function processAPPayment(array $data, array $allocations, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($data, $allocations, $actor): Payment {
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal pembayaran hutang harus lebih besar dari 0.'],
                ]);
            }

            $bankAccount = ChartOfAccount::findOrFail($data['account_id']);
            $apAccount = $this->getPayableAccount();

            $branchId = $data['branch_id']
                ?? $actor?->branch_id
                ?? auth()->user()?->branch_id
                ?? Branch::value('id');

            $paymentDate = $data['payment_date'] ?? now()->toDateString();
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : ('AP-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4)));

            $notes = $data['notes'] ?? null;

            // 1. Simpan Header Payment
            $payment = Payment::create([
                'branch_id' => $branchId,
                'type' => 'AP',
                'customer_id' => null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'account_id' => $bankAccount->id,
                'payment_date' => $paymentDate,
                'reference_number' => $referenceNumber,
                'amount' => $amount,
                'notes' => $notes,
            ]);

            // 2. Simpan Alokasi dan update tabel PurchaseOrder / GoodsReceipt (pembelian tanpa PO)
            $totalAllocated = '0.0000';
            foreach ($allocations as $alloc) {
                $poId = $alloc['purchase_order_id'] ?? null;
                $receiptId = $alloc['goods_receipt_id'] ?? null;
                $allocatedAmount = (string) ($alloc['allocated_amount'] ?? 0);

                if (bccomp($allocatedAmount, '0', 4) <= 0) {
                    continue;
                }

                $totalAllocated = bcadd($totalAllocated, $allocatedAmount, 4);

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'sale_id' => null,
                    'purchase_order_id' => $poId,
                    'goods_receipt_id' => $receiptId,
                    'allocated_amount' => $allocatedAmount,
                ]);

                if ($receiptId) {
                    $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receiptId);
                    $receipt->paid_amount = bcadd((string) ($receipt->paid_amount ?? 0), $allocatedAmount, 2);
                    $receipt->save();
                }

                if ($poId) {
                    $po = PurchaseOrder::withoutGlobalScopes()->findOrFail($poId);
                    $newPaidAmount = bcadd((string) ($po->paid_amount ?? 0), $allocatedAmount, 4);
                    $po->paid_amount = $newPaidAmount;

                    // Update payment_status
                    if (bccomp($newPaidAmount, (string) $po->total_amount, 4) >= 0) {
                        $po->payment_status = 'PAID';
                    } else {
                        $po->payment_status = 'PARTIAL';
                    }
                    $po->save();
                }
            }

            // 3. Jurnal Otomatis Berpasangan:
            // Debit: Hutang Usaha (2110) senilai amount
            // Kredit: Kas/Bank (account_id) senilai amount
            $description = "Pembayaran Hutang Supplier (AP) Ref: {$referenceNumber} via {$bankAccount->name}";
            if ($notes) {
                $description .= " - {$notes}";
            }

            $journal = $this->journalEntryService->createEntry([
                'branch_id' => $branchId,
                'user_id' => $actor?->id ?? auth()->id(),
                'transaction_date' => $paymentDate,
                'reference_number' => $referenceNumber,
                'description' => $description,
                'lines' => [
                    [
                        'chart_of_account_id' => $apAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => "Pelunasan hutang usaha {$referenceNumber}",
                    ],
                    [
                        'chart_of_account_id' => $bankAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => "Pengeluaran dana kas/bank pelunasan hutang {$referenceNumber}",
                    ],
                ],
            ]);

            $payment->update(['journal_header_id' => $journal->id]);

            return $payment->load(['allocations.purchaseOrder', 'account', 'journalHeader.journalLines', 'branch']);
        });
    }

    /**
     * Dapatkan akun Piutang Usaha (Aset Lancar).
     */
    protected function getReceivableAccount(): ChartOfAccount
    {
        return ChartOfAccount::where('code', self::ACCOUNT_RECEIVABLE_CODE)->first()
            ?? ChartOfAccount::where('type', 'asset')->where(function ($q) {
                $q->where('name', 'like', '%piutang%')->orWhere('code', 'like', '113%');
            })->first()
            ?? ChartOfAccount::firstOrCreate(
                ['code' => self::ACCOUNT_RECEIVABLE_CODE],
                ['name' => 'Piutang Usaha', 'type' => 'asset', 'is_active' => true]
            );
    }

    /**
     * Dapatkan akun Hutang Usaha / Hutang Dagang (Kewajiban Lancar).
     */
    protected function getPayableAccount(): ChartOfAccount
    {
        return ChartOfAccount::where('code', self::ACCOUNT_PAYABLE_CODE)->first()
            ?? ChartOfAccount::where('type', 'liability')->where(function ($q) {
                $q->where('name', 'like', '%hutang%')->orWhere('code', 'like', '21%');
            })->first()
            ?? ChartOfAccount::firstOrCreate(
                ['code' => self::ACCOUNT_PAYABLE_CODE],
                ['name' => 'Hutang Dagang', 'type' => 'liability', 'is_active' => true]
            );
    }
}
