<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bayar Hutang dari Buku Pemasok: nominal bebas (boleh cicil) dipotongkan
 * ke nota terlama lebih dulu (FIFO), lalu dibukukan Dr Hutang Dagang / Cr Kas-Bank.
 */
class SupplierDebtPaymentService
{
    public function __construct(
        protected SupplierLedgerService $ledgerService,
        protected ARAPPaymentService $arapPaymentService
    ) {}

    /**
     * @param array{
     *     account_id: int,
     *     amount: string|float|int,
     *     payment_date: string,
     *     notes?: string|null
     * } $data
     *
     * @throws ValidationException
     */
    public function pay(Supplier $supplier, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($supplier, $data, $actor): Payment {
            $orders = $this->ledgerService->ordersQuery($supplier)->lockForUpdate()->get();
            $directReceipts = $this->ledgerService->directReceiptsQuery($supplier)->lockForUpdate()->get();

            $openInvoices = $this->ledgerService
                ->computeInvoices($orders, $directReceipts, $this->ledgerService->unallocatedCredit($supplier))
                ->filter(fn (array $row) => bccomp($row['outstanding'], '0.00', 2) > 0)
                ->values();

            $amount = number_format((float) $data['amount'], 2, '.', '');
            $totalDebt = $openInvoices->reduce(fn (string $carry, array $row) => bcadd($carry, $row['outstanding'], 2), '0.00');

            if (bccomp($totalDebt, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ["Tidak ada sisa hutang ke {$supplier->name} yang perlu dibayar."],
                ]);
            }

            if (bccomp($amount, $totalDebt, 2) > 0) {
                $formatted = 'Rp '.number_format((float) $totalDebt, 0, ',', '.');
                throw ValidationException::withMessages([
                    'amount' => ["Nominal melebihi sisa hutang. Maksimal yang bisa dibayar: {$formatted}."],
                ]);
            }

            $allocations = $this->ledgerService->allocateFifo($openInvoices, $amount);

            $notes = isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? trim((string) $data['notes'])
                : null;

            return $this->arapPaymentService->processAPPayment([
                'branch_id' => $supplier->branch_id,
                'supplier_id' => $supplier->id,
                'account_id' => (int) $data['account_id'],
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'notes' => $notes,
            ], $allocations, $actor);
        });
    }
}
