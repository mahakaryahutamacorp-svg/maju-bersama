<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * Tampilkan riwayat pengeluaran kas biaya operasional.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $categoryId = $request->input('expense_category_id');

        $query = Expense::with(['expenseCategory.chartOfAccount', 'account', 'branch', 'journalHeader'])
            ->when($startDate, fn ($q) => $q->where('expense_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('expense_date', '<=', $endDate))
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('expenseCategory', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('expense_date')
            ->latest('id');

        $expenses = $query->paginate(15)->withQueryString();

        // Metrics untuk KPI summary card
        $allFiltered = (clone $query)->get();
        $totalExpenses = (float) $allFiltered->sum('amount');
        $expenseCount = $allFiltered->count();

        $categories = ExpenseCategory::orderBy('name')->get();

        return view('backoffice.expenses.index', [
            'currentUser'        => $user,
            'isMaster'           => $user->isMaster(),
            'expenses'           => $expenses,
            'totalExpenses'      => $totalExpenses,
            'expenseCount'       => $expenseCount,
            'categories'         => $categories,
            'search'             => $search,
            'startDate'          => $startDate,
            'endDate'            => $endDate,
            'selectedCategoryId' => $categoryId,
        ]);
    }

    /**
     * Tampilkan formulir pencatatan kas keluar biaya operasional.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Kategori biaya yang aktif beserta relasi akun bebannya
        $categories = ExpenseCategory::where('is_active', true)
            ->with('chartOfAccount')
            ->orderBy('name')
            ->get();

        // Akun Kas & Bank (Aset Lancar - 11xx)
        $accounts = ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('name', 'like', '%kas%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->orderBy('code')
            ->get();

        if ($accounts->isEmpty()) {
            $accounts = ChartOfAccount::where('type', 'asset')->orderBy('code')->get();
        }

        return view('backoffice.expenses.create', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'categories'  => $categories,
            'accounts'    => $accounts,
            'todayDate'   => now()->toDateString(),
        ]);
    }

    /**
     * Simpan transaksi kas keluar dan posting jurnal akuntansi otomatis.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'account_id'          => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'expense_date'        => ['required', 'date'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'notes'               => ['required', 'string', 'max:1000'],
        ]);

        $expense = $this->expenseService->recordExpense([
            'branch_id'           => $request->user()->branch_id,
            'user_id'             => $request->user()->id,
            'expense_category_id' => (int) $validated['expense_category_id'],
            'account_id'          => (int) $validated['account_id'],
            'amount'              => $validated['amount'],
            'expense_date'        => $validated['expense_date'],
            'reference_number'    => $validated['reference_number'] ?: null,
            'notes'               => $validated['notes'],
        ]);

        $formattedAmount = 'Rp ' . number_format((float) $expense->amount, 0, ',', '.');
        $catName = $expense->expenseCategory?->name ?? 'Biaya';

        return redirect()
            ->route('backoffice.expenses.index')
            ->with('success', "Pengeluaran kas {$formattedAmount} untuk {$catName} berhasil dibukukan dengan jurnal akuntansi otomatis.");
    }

    /**
     * Tampilkan detail voucher kas keluar dan jurnal ganda terkait.
     */
    public function show(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');

        $expense = Expense::with([
            'expenseCategory.chartOfAccount',
            'account',
            'branch',
            'journalHeader.journalLines.chartOfAccount',
        ])->findOrFail($id);

        return view('backoffice.expenses.show', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'expense'     => $expense,
        ]);
    }
}
