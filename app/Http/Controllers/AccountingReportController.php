<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingReportController extends Controller
{
    public function __construct(
        protected AccountingReportService $reportService
    ) {}

    /**
     * Display General Ledger (Buku Besar).
     */
    public function ledger(Request $request): View
    {
        $user = $request->user();
        $isMaster = $user->isMaster()
            || in_array($user->role, ['admin', 'superadmin', 'master'], true)
            || ($user->branch && ($user->branch->parent_id === null || $user->branch->code === 'PUSAT'));

        // Default date range: start of current month to today
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // Multi-tenancy enforcement: non-master users are strictly confined to their branch
        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $user->branch_id;

        $accountId = $request->filled('account_id') ? (int) $request->input('account_id') : null;

        $report = $this->reportService->getLedger($branchId, $startDate, $endDate);

        // If a specific account is requested, filter the report accounts
        if ($accountId !== null) {
            $report['accounts'] = collect($report['accounts'])
                ->filter(fn (array $item) => (int) $item['account']['id'] === $accountId)
                ->values()
                ->all();
        }

        // Ambil objek Branch dan passing activeBranchName
        $activeBranch = $branchId ? Branch::query()->find($branchId) : null;
        $activeBranchName = $activeBranch ? $activeBranch->name : 'Konsolidasi Seluruh Cabang';

        // Pastikan dropdown cabang terisi penuh dengan data dari tabel branches
        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $user->branch_id)->get();

        $accounts = ChartOfAccount::query()->orderBy('code')->get();

        return view('reports.accounting.ledger', [
            'currentUser' => $user->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'accounts' => $accounts,
            'branchId' => $branchId,
            'activeBranch' => $activeBranch,
            'activeBranchName' => $activeBranchName,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'accountId' => $accountId,
        ]);
    }

    /**
     * Display Trial Balance (Neraca Saldo).
     */
    public function trialBalance(Request $request): View
    {
        $user = $request->user();
        $isMaster = $user->isMaster()
            || in_array($user->role, ['admin', 'superadmin', 'master'], true)
            || ($user->branch && ($user->branch->parent_id === null || $user->branch->code === 'PUSAT'));

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // Multi-tenancy enforcement: non-master users are strictly confined to their branch
        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $user->branch_id;

        $report = $this->reportService->getTrialBalance($branchId, $startDate, $endDate);

        // Ambil objek Branch dan passing activeBranchName
        $activeBranch = $branchId ? Branch::query()->find($branchId) : null;
        $activeBranchName = $activeBranch ? $activeBranch->name : 'Konsolidasi Seluruh Cabang';

        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $user->branch_id)->get();

        return view('reports.accounting.trial-balance', [
            'currentUser' => $user->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'branchId' => $branchId,
            'activeBranch' => $activeBranch,
            'activeBranchName' => $activeBranchName,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display Income Statement (Laporan Laba Rugi).
     */
    public function incomeStatement(Request $request): View
    {
        $user = $request->user();
        $isMaster = $user->isMaster()
            || in_array($user->role, ['admin', 'superadmin', 'master'], true)
            || ($user->branch && ($user->branch->parent_id === null || $user->branch->code === 'PUSAT'));

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // Multi-tenancy enforcement: non-master users are strictly confined to their branch
        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $user->branch_id;

        $report = $this->reportService->getIncomeStatement($branchId, $startDate, $endDate);

        // Ambil objek Branch dan passing activeBranchName
        $activeBranch = $branchId ? Branch::query()->find($branchId) : null;
        $activeBranchName = $activeBranch ? $activeBranch->name : 'Konsolidasi Seluruh Cabang';

        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $user->branch_id)->get();

        return view('reports.accounting.income-statement', [
            'currentUser' => $user->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'branchId' => $branchId,
            'activeBranch' => $activeBranch,
            'activeBranchName' => $activeBranchName,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
