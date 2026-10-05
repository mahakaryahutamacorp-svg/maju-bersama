<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalHeader;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchasePayableController extends Controller
{
    private const ACCOUNT_CASH = '1110';

    private const ACCOUNT_BANK = '1120';

    private const ACCOUNT_PAYABLE = '2110';

    /**
     * Display a listing of distributor payables (Accounts Payable).
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Purchase::with(['distributor', 'branch', 'payments'])
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('distributor', fn ($d) => $d->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status && in_array($status, ['unpaid', 'partial', 'paid']), function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->latest('id');

        $purchases = $query->paginate(15)->withQueryString();

        // Summary Cards Metrics
        $allPurchases = Purchase::with('payments')->get();

        // 1. Total Hutang Aktif (Total sisa hutang dari invoice unpaid & partial)
        $totalActiveDebt = (float) $allPurchases
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum(fn ($p) => $p->remaining_debt);

        // 2. Total Jatuh Tempo Minggu Ini (Due date <= 7 hari ke depan atau overdue)
        $dueThreshold = now()->addDays(7)->toDateString();
        $totalDueThisWeek = (float) $allPurchases
            ->whereIn('status', ['unpaid', 'partial'])
            ->filter(fn ($p) => ! empty($p->due_date) && $p->due_date->toDateString() <= $dueThreshold)
            ->sum(fn ($p) => $p->remaining_debt);

        // 3. Total Lunas Bulan Ini
        $totalPaidThisMonth = (float) $allPurchases
            ->where('status', 'paid')
            ->filter(fn ($p) => $p->updated_at && $p->updated_at->month === now()->month && $p->updated_at->year === now()->year)
            ->sum('total_amount');

        $distributors = Supplier::orderBy('name')->get();

        return view('purchases.payables', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'purchases' => $purchases,
            'totalActiveDebt' => $totalActiveDebt,
            'totalDueThisWeek' => $totalDueThisWeek,
            'totalPaidThisMonth' => $totalPaidThisMonth,
            'distributors' => $distributors,
        ]);
    }

    /**
     * Store a payment / installment via Web / AJAX form.
     */
    public function storePayment(Request $request, Purchase $purchase): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,transfer,giro'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);
        $paymentMethod = strtolower($validated['payment_method'] ?? 'cash');
        $paymentDate = $validated['payment_date'] ?? now()->toDateString();
        $refNumber = $validated['reference_number'] ?? ('PAY-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT));

        $remainingDebt = $purchase->remaining_debt;

        if ($amount > $remainingDebt) {
            $msg = sprintf(
                'Nominal pembayaran (Rp %s) tidak boleh melebihi sisa hutang (Rp %s).',
                number_format($amount, 2, ',', '.'),
                number_format($remainingDebt, 2, ',', '.')
            );

            if ($request->wantsJson()) {
                return response()->json(['message' => $msg, 'errors' => ['amount' => [$msg]]], 422);
            }

            return back()->withErrors(['amount' => $msg])->withInput();
        }

        $result = DB::transaction(function () use ($purchase, $amount, $paymentDate, $paymentMethod, $refNumber, $validated, $user) {
            $lockedPurchase = Purchase::withoutGlobalScopes()
                ->where('id', $purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            // 2. Update Purchase paid_amount & status
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

            // 3. Double-entry Journal: Dr. Hutang Dagang (2110) | Cr. Kas/Bank (1110/1120)
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
                'journal' => $journal,
            ];
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pembayaran hutang berhasil dicatat dan jurnal akuntansi balance.',
                'status' => 'success',
                'data' => $result,
            ], 201);
        }

        return redirect()->route('purchases.payables')->with('success', 'Pembayaran cicilan berhasil dicatat.');
    }
}
