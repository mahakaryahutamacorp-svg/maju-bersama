<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\CashTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashTransactionController extends Controller
{
    public function __construct(
        protected CashTransactionService $cashTransactionService
    ) {}

    /**
     * Tampilkan riwayat transaksi kas / alihkan ke daftar mutasi kas & bank.
     */
    public function index(Request $request): RedirectResponse|View
    {
        return redirect()->route('backoffice.cash-transfers.index');
    }

    /**
     * Tampilkan formulir pencatatan transaksi kas (Mendukung tipe 'TRANSFER', 'IN', 'OUT').
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $defaultType = strtoupper($request->query('type', 'TRANSFER'));

        // 1. Akun Kas & Bank (Aset Lancar - 11xx) yang aktif
        $cashBankAccounts = ChartOfAccount::where('type', 'asset')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('name', 'like', '%kas%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->orderBy('code')
            ->get();

        if ($cashBankAccounts->isEmpty()) {
            $cashBankAccounts = ChartOfAccount::where('type', 'asset')
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        // 2. Seluruh akun aktif untuk baris dinamis (Akun Lawan) pada Kas Masuk/Keluar
        $allAccounts = ChartOfAccount::where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('backoffice.cash-transactions.create', [
            'currentUser'      => $user,
            'isMaster'         => $user->isMaster(),
            'accounts'         => $cashBankAccounts,
            'cashBankAccounts' => $cashBankAccounts,
            'allAccounts'      => $allAccounts,
            'defaultType'      => $defaultType,
            'todayDate'        => now()->toDateString(),
        ]);
    }

    /**
     * Simpan transaksi kas (TRANSFER / IN / OUT) dan bukukan jurnal akuntansi otomatis.
     */
    public function store(Request $request): RedirectResponse
    {
        $type = strtoupper((string) $request->input('type', 'TRANSFER'));

        if ($type === 'TRANSFER') {
            $validated = $request->validate([
                'type'             => ['nullable', 'string'],
                'from_account_id'  => ['required', 'integer', 'exists:chart_of_accounts,id'],
                'to_account_id'    => ['required', 'integer', 'exists:chart_of_accounts,id', 'different:from_account_id'],
                'amount'           => ['required', 'numeric', 'min:0.01'],
                'transaction_date' => ['nullable', 'date'],
                'transfer_date'    => ['nullable', 'date'],
                'reference_number' => ['nullable', 'string', 'max:100'],
                'notes'            => ['nullable', 'string', 'max:1000'],
            ], [
                'to_account_id.different' => 'Akun tujuan transfer tidak boleh sama dengan akun sumber (asal).',
                'amount.min'              => 'Nominal transfer kas/bank harus lebih besar dari 0.',
            ]);

            $date = $validated['transaction_date'] ?? $validated['transfer_date'] ?? now()->toDateString();

            $transfer = $this->cashTransactionService->storeTransfer([
                'branch_id'        => $request->user()->branch_id,
                'user_id'          => $request->user()->id,
                'from_account_id'  => (int) $validated['from_account_id'],
                'to_account_id'    => (int) $validated['to_account_id'],
                'amount'           => $validated['amount'],
                'transaction_date' => $date,
                'transfer_date'    => $date,
                'reference_number' => ! empty($validated['reference_number']) ? trim($validated['reference_number']) : null,
                'notes'            => ! empty($validated['notes']) ? trim($validated['notes']) : null,
            ]);

            $formattedAmount = 'Rp ' . number_format((float) $transfer->amount, 0, ',', '.');
            $fromName = $transfer->fromAccount?->name ?? 'Kas Sumber';
            $toName = $transfer->toAccount?->name ?? 'Kas Tujuan';

            return redirect()
                ->route('backoffice.cash-transfers.index')
                ->with('success', "Mutasi transfer kas senilai {$formattedAmount} dari {$fromName} ke {$toName} berhasil dibukukan dengan nomor ref: {$transfer->reference_number}.");
        }

        // Penanganan tipe Kas Masuk (IN) / Kas Keluar (OUT)
        $validated = $request->validate([
            'type'             => ['required', 'in:IN,OUT'],
            'account_id'       => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'lines'            => ['required', 'array', 'min:1'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.amount'              => ['required', 'numeric', 'min:0.01'],
            'lines.*.memo'                => ['nullable', 'string', 'max:500'],
        ]);

        $this->cashTransactionService->storeTransaction([
            'branch_id'        => $request->user()->branch_id,
            'user_id'          => $request->user()->id,
            'type'             => $type,
            'account_id'       => (int) $validated['account_id'],
            'transaction_date' => $validated['transaction_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ], $validated['lines']);

        $label = $type === 'IN' ? 'Kas Masuk' : 'Kas Keluar';
        return redirect()
            ->route('backoffice.cash-transfers.index')
            ->with('success', "Transaksi {$label} berhasil dibukukan dengan jurnal akuntansi berpasangan.");
    }
}
