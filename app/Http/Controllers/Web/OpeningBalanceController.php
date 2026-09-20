<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function __construct(
        protected OpeningBalanceService $openingBalanceService
    ) {}

    /**
     * Tampilkan formulir input saldo awal sistem (Opening Balance).
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $branchId = $user->branch_id;

        $hasOpeningBalance = OpeningBalanceService::hasOpeningBalance($branchId);
        $existingJournal = $hasOpeningBalance 
            ? OpeningBalanceService::getExistingOpeningBalance($branchId) 
            : null;

        // Ambil akun-akun standar untuk referensi/dropdown opsional
        $cashAccounts = ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '111%')
                  ->orWhere('name', 'like', '%kas%');
            })
            ->get();

        $bankAccounts = ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '112%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->get();

        $inventoryAccount = ChartOfAccount::where('code', OpeningBalanceService::CODE_INVENTORY)->first()
            ?? ChartOfAccount::where('type', 'asset')->where('name', 'like', '%persediaan%')->first();

        $payableAccount = ChartOfAccount::where('code', OpeningBalanceService::CODE_PAYABLE)->first()
            ?? ChartOfAccount::where('type', 'liability')->where('name', 'like', '%hutang%')->first();

        $equityAccount = ChartOfAccount::where('code', OpeningBalanceService::CODE_EQUITY)->first()
            ?? ChartOfAccount::where('type', 'equity')->first();

        return view('backoffice.opening-balances.create', [
            'currentUser'       => $user,
            'isMaster'          => $user->isMaster(),
            'hasOpeningBalance' => $hasOpeningBalance,
            'existingJournal'   => $existingJournal,
            'cashAccounts'      => $cashAccounts,
            'bankAccounts'      => $bankAccounts,
            'inventoryAccount'  => $inventoryAccount,
            'payableAccount'    => $payableAccount,
            'equityAccount'     => $equityAccount,
            'todayDate'         => now()->toDateString(),
        ]);
    }

    /**
     * Simpan dan posting jurnal saldo awal ganda ke buku besar.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_in_drawer'       => ['nullable', 'numeric', 'min:0'],
            'bank_bca'             => ['nullable', 'numeric', 'min:0'],
            'inventory'            => ['nullable', 'numeric', 'min:0'],
            'payable'              => ['nullable', 'numeric', 'min:0'],
            'transaction_date'     => ['required', 'date'],
            'reference_number'     => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'cash_account_id'      => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'bank_account_id'      => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'payable_account_id'   => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'equity_account_id'    => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ], [
            'cash_in_drawer.min' => 'Saldo kas tidak boleh negatif.',
            'bank_bca.min'       => 'Saldo bank tidak boleh negatif.',
            'inventory.min'      => 'Nilai persediaan tidak boleh negatif.',
            'payable.min'        => 'Nilai hutang tidak boleh negatif.',
        ]);

        $journal = $this->openingBalanceService->postOpeningBalance([
            'branch_id'            => $request->user()->branch_id,
            'user_id'              => $request->user()->id,
            'transaction_date'     => $validated['transaction_date'],
            'reference_number'     => ! empty($validated['reference_number']) ? trim($validated['reference_number']) : null,
            'notes'                => ! empty($validated['notes']) ? trim($validated['notes']) : null,
            'cash_in_drawer'       => $validated['cash_in_drawer'] ?? 0,
            'bank_bca'             => $validated['bank_bca'] ?? 0,
            'inventory'            => $validated['inventory'] ?? 0,
            'payable'              => $validated['payable'] ?? 0,
            'cash_account_id'      => $validated['cash_account_id'] ?? null,
            'bank_account_id'      => $validated['bank_account_id'] ?? null,
            'inventory_account_id' => $validated['inventory_account_id'] ?? null,
            'payable_account_id'   => $validated['payable_account_id'] ?? null,
            'equity_account_id'    => $validated['equity_account_id'] ?? null,
        ], $request->user());

        return redirect()
            ->route('backoffice.opening-balances.create')
            ->with('success', "Saldo awal sistem berhasil dibukukan dengan nomor referensi jurnal {$journal->reference_number} secara seimbang.");
    }
}
