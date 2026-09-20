<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashTransfer;
use App\Models\ChartOfAccount;
use App\Services\CashTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashTransferController extends Controller
{
    public function __construct(
        protected CashTransferService $cashTransferService
    ) {}

    /**
     * Tampilkan riwayat mutasi / transfer antar kas & bank.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountId = $request->input('account_id');

        $query = CashTransfer::with([
            'fromAccount',
            'toAccount',
            'branch',
            'journalHeader.journalLines.chartOfAccount',
        ])
            ->when($startDate, fn ($q) => $q->where('transfer_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('transfer_date', '<=', $endDate))
            ->when($accountId, function ($q, $accountId) {
                $q->where(function ($sub) use ($accountId) {
                    $sub->where('from_account_id', $accountId)
                        ->orWhere('to_account_id', $accountId);
                });
            })
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('fromAccount', fn ($acc) => $acc->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('toAccount', fn ($acc) => $acc->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('transfer_date')
            ->latest('id');

        $transfers = $query->paginate(15)->withQueryString();

        // Metrik akumulasi untuk KPI Card
        $allFiltered = (clone $query)->get();
        $totalTransferAmount = (float) $allFiltered->sum('amount');
        $transferCount = $allFiltered->count();

        // Daftar akun kas & bank untuk opsi filter
        $accounts = ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('name', 'like', '%kas%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        if ($accounts->isEmpty()) {
            $accounts = ChartOfAccount::where('type', 'asset')->where('is_active', true)->orderBy('code')->get();
        }

        return view('backoffice.cash-transfers.index', [
            'currentUser'         => $user,
            'isMaster'            => $user->isMaster(),
            'transfers'           => $transfers,
            'totalTransferAmount' => $totalTransferAmount,
            'transferCount'       => $transferCount,
            'accounts'            => $accounts,
            'search'              => $search,
            'startDate'           => $startDate,
            'endDate'             => $endDate,
            'selectedAccountId'   => $accountId,
        ]);
    }

    /**
     * Tampilkan formulir pencatatan mutasi kas & bank.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Akun Kas & Bank (Aset Lancar - 11xx) yang aktif
        $accounts = ChartOfAccount::where('type', 'asset')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('name', 'like', '%kas%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->orderBy('code')
            ->get();

        if ($accounts->isEmpty()) {
            $accounts = ChartOfAccount::where('type', 'asset')
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        return view('backoffice.cash-transfers.create', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'accounts'    => $accounts,
            'todayDate'   => now()->toDateString(),
        ]);
    }

    /**
     * Simpan mutasi kas & bank dan bukukan jurnal akuntansi otomatis.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_account_id'  => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'to_account_id'    => ['required', 'integer', 'exists:chart_of_accounts,id', 'different:from_account_id'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'transfer_date'    => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ], [
            'to_account_id.different' => 'Akun tujuan transfer tidak boleh sama dengan akun asal.',
            'amount.min'              => 'Nominal transfer kas/bank harus lebih besar dari 0.',
        ]);

        $cashTransfer = $this->cashTransferService->processTransfer([
            'branch_id'        => $request->user()->branch_id,
            'user_id'          => $request->user()->id,
            'from_account_id'  => (int) $validated['from_account_id'],
            'to_account_id'    => (int) $validated['to_account_id'],
            'amount'           => $validated['amount'],
            'transfer_date'    => $validated['transfer_date'],
            'reference_number' => ! empty($validated['reference_number']) ? trim($validated['reference_number']) : null,
            'notes'            => ! empty($validated['notes']) ? trim($validated['notes']) : null,
        ]);

        $formattedAmount = 'Rp ' . number_format((float) $cashTransfer->amount, 0, ',', '.');
        $fromName = $cashTransfer->fromAccount?->name ?? 'Kas Asal';
        $toName = $cashTransfer->toAccount?->name ?? 'Kas Tujuan';

        return redirect()
            ->route('backoffice.cash-transfers.index')
            ->with('success', "Mutasi kas senilai {$formattedAmount} dari {$fromName} ke {$toName} berhasil dibukukan dengan nomor ref: {$cashTransfer->reference_number}.");
    }

    /**
     * Tampilkan detail voucher mutasi kas dan jurnal akuntansi terkait.
     */
    public function show(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');

        $transfer = CashTransfer::with([
            'fromAccount',
            'toAccount',
            'branch',
            'journalHeader.journalLines.chartOfAccount',
        ])->findOrFail($id);

        return view('backoffice.cash-transfers.show', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'transfer'    => $transfer,
        ]);
    }
}
