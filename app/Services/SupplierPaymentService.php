<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service to process supplier payment (Pelunasan Hutang Supplier).
 *
 * Atomically:
 * 1. Creates SupplierPayment record.
 * 2. Creates balanced double-entry accounting journal:
 *    Dr 2110 Hutang Dagang (liability decreases)
 *    Cr [chart_of_account_id] Kas / Bank (asset decreases)
 */
class SupplierPaymentService
{
    public const ACCOUNT_PAYABLE = '2110'; // Hutang Dagang

    /**
     * Process supplier payment atomically.
     *
     * @param array{
     *     branch_id?: int|null,
     *     supplier_id: int,
     *     chart_of_account_id: int,
     *     payment_date: string,
     *     payment_method: string,
     *     amount: float|string|numeric,
     *     reference_number?: string|null,
     *     notes?: string|null
     * } $data
     * @param User|null $actor
     * @return SupplierPayment
     */
    public function processPayment(array $data, ?User $actor = null): SupplierPayment
    {
        return DB::transaction(function () use ($data, $actor): SupplierPayment {
            $supplier = Supplier::withoutGlobalScopes()->findOrFail($data['supplier_id']);
            $account = ChartOfAccount::findOrFail($data['chart_of_account_id']);
            
            $payableAccount = ChartOfAccount::where('code', self::ACCOUNT_PAYABLE)->first()
                ?? ChartOfAccount::firstOrCreate(['code' => self::ACCOUNT_PAYABLE], ['name' => 'Hutang Dagang', 'type' => 'liability']);

            $branchId = $data['branch_id'] 
                ?? $actor?->branch_id 
                ?? $supplier->branch_id 
                ?? Branch::first()?->id;

            $amount = (string) $data['amount'];

            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal pembayaran harus lebih besar dari 0.'],
                ]);
            }

            $referenceNumber = ! empty($data['reference_number'])
                ? $data['reference_number']
                : ('PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

            $payment = SupplierPayment::create([
                'branch_id'           => $branchId,
                'user_id'             => $actor?->id,
                'supplier_id'         => $supplier->id,
                'chart_of_account_id' => $account->id,
                'payment_date'        => $data['payment_date'],
                'payment_method'      => $data['payment_method'],
                'amount'              => $amount,
                'reference_number'    => $referenceNumber,
                'notes'               => $data['notes'] ?? null,
            ]);

            // Create balanced double-entry journal
            $methodLabel = ucfirst($payment->payment_method);
            $description = "Pembayaran Hutang Supplier: {$supplier->name} via {$account->name} [{$methodLabel}]";

            $journal = JournalHeader::create([
                'branch_id'        => $branchId,
                'user_id'          => $actor?->id,
                'transaction_date' => $payment->payment_date,
                'reference_number' => $payment->reference_number,
                'description'      => $description,
            ]);

            $journal->journalLines()->createMany([
                [
                    'chart_of_account_id' => $payableAccount->id,
                    'debit'               => $amount,
                    'credit'              => 0,
                    'memo'                => "Pelunasan hutang dagang kepada supplier {$supplier->name}",
                ],
                [
                    'chart_of_account_id' => $account->id,
                    'debit'               => 0,
                    'credit'              => $amount,
                    'memo'                => "Pengeluaran dana pembayaran via {$account->name}",
                ],
            ]);

            $payment->update(['journal_header_id' => $journal->id]);

            return $payment->load(['supplier', 'chartOfAccount', 'journalHeader', 'branch']);
        });
    }
}
