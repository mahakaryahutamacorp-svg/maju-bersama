<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePaymentController extends Controller
{
    private const ACCOUNT_CASH = '1110';

    private const ACCOUNT_BANK = '1120';

    private const ACCOUNT_PAYABLE = '2110';

    /**
     * Display a listing of purchase payments.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = PurchasePayment::with(['purchase', 'branch', 'creator'])
            ->latest('id')
            ->get();

        return response()->json([
            'message' => 'Purchase payments retrieved successfully.',
            'data' => $payments,
        ]);
    }

    /**
     * Store a payment / installment for a purchase (Accounts Payable settlement).
     */
    public function store(Request $request, ?Purchase $purchase = null): JsonResponse
    {
        $validated = $request->validate([
            'purchase_id' => [$purchase ? 'nullable' : 'required', 'integer', 'exists:purchases,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,transfer,giro'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $purchaseId = $purchase ? $purchase->id : $validated['purchase_id'];
        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);
        $paymentMethod = strtolower($validated['payment_method'] ?? 'cash');
        $paymentDate = $validated['payment_date'] ?? now()->toDateString();
        $refNumber = $validated['reference_number'] ?? ('PAY-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));

        $result = DB::transaction(function () use ($purchaseId, $amount, $paymentDate, $paymentMethod, $refNumber, $validated, $user) {
            /** @var Purchase $lockedPurchase */
            $lockedPurchase = Purchase::withoutGlobalScopes()
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingDebt = $lockedPurchase->remaining_debt;

            // Strict validation: Amount cannot exceed remaining debt
            if ($amount > $remainingDebt) {
                throw ValidationException::withMessages([
                    'amount' => [
                        sprintf(
                            'Nominal pembayaran (Rp %s) tidak boleh melebihi sisa hutang (Rp %s).',
                            number_format($amount, 2, ',', '.'),
                            number_format($remainingDebt, 2, ',', '.')
                        ),
                    ],
                ]);
            }

            // 1. Create PurchasePayment record
            $payment = PurchasePayment::create([
                'purchase_id' => $lockedPurchase->id,
                'branch_id' => $lockedPurchase->branch_id ?? ($user->branch_id ?? 1),
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'reference_number' => $refNumber,
                'notes' => $validated['notes'] ?? ('Pembayaran cicilan hutang distributor faktur '.$lockedPurchase->invoice_number),
                'created_by' => $user->id,
            ]);

            // 2. Update Purchase paid_amount & dynamic status
            $newPaidAmount = round((float) $lockedPurchase->paid_amount + $amount, 2);
            $lockedPurchase->paid_amount = $newPaidAmount;

            if ($newPaidAmount >= (float) $lockedPurchase->total_amount) {
                $lockedPurchase->status = 'paid';
            } elseif ($newPaidAmount > 0) {
                $lockedPurchase->status = 'partial';
            } else {
                $lockedPurchase->status = 'unpaid';
            }

            $lockedPurchase->save();

            // 3. Accounting Double-Entry Journal Integration:
            // Dr. Hutang Dagang (2110) [Liability Decreases]
            //   Cr. Kas/Bank (1110/1120) [Asset Decreases]
            $payableAccount = ChartOfAccount::firstOrCreate(
                ['code' => self::ACCOUNT_PAYABLE],
                ['name' => 'Hutang Dagang', 'type' => 'liability']
            );

            $creditAccountCode = in_array($paymentMethod, ['bank', 'transfer', 'giro'], true)
                ? self::ACCOUNT_BANK
                : self::ACCOUNT_CASH;

            $creditAccountName = $creditAccountCode === self::ACCOUNT_BANK ? 'Bank' : 'Kas';

            $cashOrBankAccount = ChartOfAccount::firstOrCreate(
                ['code' => $creditAccountCode],
                ['name' => $creditAccountName, 'type' => 'asset']
            );

            $journal = JournalHeader::create([
                'branch_id' => $lockedPurchase->branch_id ?? ($user->branch_id ?? 1),
                'user_id' => $user->id,
                'transaction_date' => $paymentDate,
                'reference_number' => $refNumber,
                'description' => 'Pembayaran Hutang Distributor (Faktur: '.$lockedPurchase->invoice_number.')',
            ]);

            $formattedAmount = number_format($amount, 2, '.', '');

            $journal->journalLines()->createMany([
                [
                    'chart_of_account_id' => $payableAccount->id,
                    'debit' => $formattedAmount,
                    'credit' => '0.00',
                    'memo' => 'Pelunasan/Cicilan Hutang Dagang '.$lockedPurchase->invoice_number,
                ],
                [
                    'chart_of_account_id' => $cashOrBankAccount->id,
                    'debit' => '0.00',
                    'credit' => $formattedAmount,
                    'memo' => 'Pengeluaran '.$creditAccountName.' untuk bayar hutang distributor',
                ],
            ]);

            return [
                'payment' => $payment,
                'purchase' => $lockedPurchase->fresh(['payments']),
                'journal' => $journal->load('journalLines'),
            ];
        });

        return response()->json([
            'message' => 'Pembayaran hutang distributor berhasil diproses.',
            'status' => 'success',
            'data' => $result,
        ], 201);
    }
}
