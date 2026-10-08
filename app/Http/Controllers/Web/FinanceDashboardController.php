<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualJournalRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\JournalHeader;
use App\Services\FinanceDashboardService;
use App\Services\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    public function __construct(
        protected FinanceDashboardService $dashboardService,
        protected JournalPostingService $journalPostingService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = $user->isMaster();

        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : (int) $user->branch_id)
            : (int) $user->branch_id;

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $tab = $request->input('tab', 'kas_bank');

        $dashboard = $this->dashboardService->build($user, $branchId, $startDate, $endDate);

        $cashBankAccounts = ChartOfAccount::query()->cashAndBank()->orderBy('code')->get();

        return view('backoffice.finance.dashboard', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'active' => 'finance',
            'tab' => in_array($tab, ['kas_bank', 'jurnal', 'pengeluaran', 'laporan'], true) ? $tab : 'kas_bank',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedBranchId' => $branchId,
            'branches' => $isMaster ? Branch::query()->orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'dashboard' => $dashboard,
            'expenseCategories' => ExpenseCategory::query()->where('is_active', true)->with('chartOfAccount')->orderBy('name')->get(),
            'cashBankAccounts' => $cashBankAccounts,
            'coaAccounts' => ChartOfAccount::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'todayDate' => now()->toDateString(),
        ]);
    }

    public function storeJournal(StoreManualJournalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $branchId = $user->isMaster() && ! empty($validated['branch_id'])
            ? (int) $validated['branch_id']
            : (int) $user->branch_id;

        $reference = ! empty($validated['reference_number'])
            ? (string) $validated['reference_number']
            : $this->generateJournalReference();

        $this->journalPostingService->post([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'transaction_date' => $validated['transaction_date'],
            'reference_number' => $reference,
            'description' => $validated['description'],
            'lines' => $validated['lines'],
        ]);

        return redirect()
            ->route('backoffice.finance.dashboard', ['tab' => 'jurnal'])
            ->with('success', "Jurnal manual {$reference} berhasil diposting.");
    }

    private function generateJournalReference(): string
    {
        do {
            $ref = 'JU-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (JournalHeader::query()->where('reference_number', $ref)->exists());

        return $ref;
    }
}
