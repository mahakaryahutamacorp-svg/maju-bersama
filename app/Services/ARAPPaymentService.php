<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ARAPPaymentService
{
    public const ACCOUNT_RECEIVABLE_CODE = '1130';
    public const ACCOUNT_PAYABLE_CODE = '2110';

    public function __construct(
        protected JournalEntryService $journalEntryService
    ) {}

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
     * @param array $data
     * @param array $allocations
     * @param User|null $actor
     * @return Payment
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

            $bankAccount = ChartOfAccount::findOrFail($data['account_id']);
            $arAccount = $this->getReceivableAccount();

            $branchId = $data['branch_id']
                ?? $actor?->branch_id
                ?? auth()->user()?->branch_id
                ?? Branch::value('id');

            $paymentDate = $data['payment_date'] ?? now()->toDateString();
            $referenceNumber = ! empty($data['reference_number'])
                ? (string) $data['reference_number']
                : ('AR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

            $notes = $data['notes'] ?? null;

            // 1. Simpan Header Payment
            $payment = Payment::create([
                'branch_id'        => $branchId,
                'type'             => 'AR',
                'customer_id'      => $data['customer_id'] ?? null,
                'supplier_id'      => null,
                'account_id'       => $bankAccount->id,
                'payment_date'     => $paymentDate,
                'reference_number' => $referenceNumber,
                'amount'           => $amount,
                'notes'            => $notes,
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
                    'payment_id'       => $payment->id,
                    'sale_id'          => $saleId,
                    'purchase_order_id'=> null,
                    'allocated_amount' => $allocatedAmount,
                ]);

                if ($saleId) {
                    $sale = Sale::withoutGlobalScopes()->findOrFail($saleId);
                    $newPaidAmount = bcadd((string) ($sale->paid_amount ?? 0), $allocatedAmount, 4);
                    $sale->paid_amount = $newPaidAmount;

                    // Update payment_status
                    if (bccomp($newPaidAmount, (string) $sale->total_amount, 4) >= 0) {
                        $sale->payment_status = 'PAID';
                    } else {
                        $sale->payment_status = 'PARTIAL';
                    }
                    $sale->save();
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
                'branch_id'        => $branchId,
                'user_id'          => $actor?->id ?? auth()->id(),
                'transaction_date' => $paymentDate,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => [
                    [
                        'chart_of_account_id' => $bankAccount->id,
                        'debit'               => $amount,
                        'credit'              => 0,
                        'memo'                => "Penerimaan dana kas/bank piutang {$referenceNumber}",
                    ],
                    [
                        'chart_of_account_id' => $arAccount->id,
                        'debit'               => 0,
                        'credit'              => $amount,
                        'memo'                => "Pengurangan piutang usaha {$referenceNumber}",
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
     * @param array $data
     * @param array $allocations
     * @param User|null $actor
     * @return Payment
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
                : ('AP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

            $notes = $data['notes'] ?? null;

            // 1. Simpan Header Payment
            $payment = Payment::create([
                'branch_id'        => $branchId,
                'type'             => 'AP',
                'customer_id'      => null,
                'supplier_id'      => $data['supplier_id'] ?? null,
                'account_id'       => $bankAccount->id,
                'payment_date'     => $paymentDate,
                'reference_number' => $referenceNumber,
                'amount'           => $amount,
                'notes'            => $notes,
            ]);

            // 2. Simpan Alokasi dan update tabel PurchaseOrder
            $totalAllocated = '0.0000';
            foreach ($allocations as $alloc) {
                $poId = $alloc['purchase_order_id'] ?? null;
                $allocatedAmount = (string) ($alloc['allocated_amount'] ?? 0);

                if (bccomp($allocatedAmount, '0', 4) <= 0) {
                    continue;
                }

                $totalAllocated = bcadd($totalAllocated, $allocatedAmount, 4);

                PaymentAllocation::create([
                    'payment_id'        => $payment->id,
                    'sale_id'           => null,
                    'purchase_order_id' => $poId,
                    'allocated_amount'  => $allocatedAmount,
                ]);

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
                'branch_id'        => $branchId,
                'user_id'          => $actor?->id ?? auth()->id(),
                'transaction_date' => $paymentDate,
                'reference_number' => $referenceNumber,
                'description'      => $description,
                'lines'            => [
                    [
                        'chart_of_account_id' => $apAccount->id,
                        'debit'               => $amount,
                        'credit'              => 0,
                        'memo'                => "Pelunasan hutang usaha {$referenceNumber}",
                    ],
                    [
                        'chart_of_account_id' => $bankAccount->id,
                        'debit'               => 0,
                        'credit'              => $amount,
                        'memo'                => "Pengeluaran dana kas/bank pelunasan hutang {$referenceNumber}",
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
