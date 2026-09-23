<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\JournalHeader;
use App\Services\Reports\BalanceSheetService;
use App\Services\Reports\CashFlowService;
use App\Services\Reports\IncomeStatementService;
use App\Services\Reports\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $currentUser = $request->user();

        return view('backoffice.reports.index', [
            'currentUser' => $currentUser,
        ]);
    }

    public function incomeStatement(Request $request, IncomeStatementService $service): View
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster()
            || in_array($currentUser->role, ['admin', 'superadmin', 'master'], true)
            || ($currentUser->branch && ($currentUser->branch->parent_id === null || $currentUser->branch->code === 'PUSAT'));

        // Parameter start_date & end_date dari query string (default: bulan berjalan)
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        // Multi-tenancy branch enforcement
        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $currentUser->branch_id;

        $report = $service->generate($startDate, $endDate, $branchId);

        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $currentUser->branch_id)->get();

        return view('backoffice.reports.income-statement', [
            'currentUser' => $currentUser->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedBranchId' => $branchId,
        ]);
    }

    public function trialBalance(Request $request, TrialBalanceService $service): View
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster()
            || in_array($currentUser->role, ['admin', 'superadmin', 'master'], true)
            || ($currentUser->branch && ($currentUser->branch->parent_id === null || $currentUser->branch->code === 'PUSAT'));

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $currentUser->branch_id;

        $report = $service->generate($startDate, $endDate, $branchId);

        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $currentUser->branch_id)->get();

        return view('backoffice.reports.trial-balance', [
            'currentUser' => $currentUser->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedBranchId' => $branchId,
        ]);
    }

    public function balanceSheet(Request $request, BalanceSheetService $service): View
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster()
            || in_array($currentUser->role, ['admin', 'superadmin', 'master'], true)
            || ($currentUser->branch && ($currentUser->branch->parent_id === null || $currentUser->branch->code === 'PUSAT'));

        $startDate = $request->input('start_date', now()->startOfYear()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $currentUser->branch_id;

        $report = $service->generate($endDate, $branchId, $startDate);

        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $currentUser->branch_id)->get();

        return view('backoffice.reports.balance-sheet', [
            'currentUser' => $currentUser->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedBranchId' => $branchId,
        ]);
    }

    public function cashFlow(Request $request, CashFlowService $service): View
    {
        $currentUser = $request->user();
        $isMaster = $currentUser->isMaster()
            || in_array($currentUser->role, ['admin', 'superadmin', 'master'], true)
            || ($currentUser->branch && ($currentUser->branch->parent_id === null || $currentUser->branch->code === 'PUSAT'));

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $branchId = $isMaster
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $currentUser->branch_id;

        $report = $service->generate($startDate, $endDate, $branchId);

        $branches = $isMaster
            ? Branch::query()->orderBy('id')->get()
            : Branch::query()->where('id', $currentUser->branch_id)->get();

        return view('backoffice.reports.cash-flow', [
            'currentUser' => $currentUser->load('branch'),
            'isMaster' => $isMaster,
            'report' => $report,
            'branches' => $branches,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedBranchId' => $branchId,
        ]);
    }

    public function journal(Request $request): View
    {
        $user = $request->user();

        $headers = JournalHeader::with([
            'journalLines.chartOfAccount',
            'user',
        ])
            ->when(! $user->isMaster(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        return view('report.journal', compact('headers'));
    }
}