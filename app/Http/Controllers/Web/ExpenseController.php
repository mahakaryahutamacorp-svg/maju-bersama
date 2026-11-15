<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\BranchCashAccountService;
use App\Services\BranchExpenseSetupService;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService,
        protected BranchExpenseSetupService $branchExpenseSetup,
        protected BranchCashAccountService $branchCashAccounts,
    ) {}

    /**
     * Tampilkan riwayat pengeluaran kas biaya operasional.
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

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

        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $expenses->items(),
                'total' => $expenses->total(),
                'totalExpenses' => $totalExpenses,
            ]);
        }

        return view('backoffice.expenses.index', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'expenseCount' => $expenseCount,
            'categories' => $categories,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedCategoryId' => $categoryId,
        ]);
    }

    /**
     * Tampilkan formulir pencatatan kas keluar biaya operasional.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);

        $user = $request->user()->load('branch');
        $branchId = $user->isMaster() && $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : (int) $user->branch_id;

        if ($branchId) {
            $this->branchExpenseSetup->ensureForBranch($branchId);
        }

        // Kategori biaya yang aktif beserta relasi akun bebannya
        $categories = ExpenseCategory::where('is_active', true)
            ->with('chartOfAccount')
            ->orderBy('name')
            ->get();

        $accounts = $branchId
            ? $this->branchCashAccounts->listForBranch($branchId)
            : ChartOfAccount::query()->whereIn('id', ChartOfAccount::cashAccountIds())->orderBy('code')->get();

        $isMaster = $user->isMaster();
        $branches = $isMaster ? \App\Models\Branch::orderBy('name')->get() : collect([$user->branch]);

        return view('backoffice.expenses.create', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'categories' => $categories,
            'accounts' => $accounts,
            'branches' => $branches,
            'todayDate' => now()->toDateString(),
        ]);
    }

    /**
     * Simpan transaksi kas keluar dan posting jurnal akuntansi otomatis.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Expense::class);

        $user = $request->user();
        $isMaster = $user->isMaster();

        $branchId = ($isMaster && $request->filled('branch_id'))
            ? (int) $request->input('branch_id')
            : (int) $user->branch_id;

        if ($branchId <= 0) {
            abort(422, 'Cabang tidak valid untuk pencatatan pengeluaran.');
        }

        $this->branchExpenseSetup->ensureForBranch($branchId);

        $defaultCashAccountId = $this->branchCashAccounts->defaultForBranch($branchId)->id;

        $validated = $request->validate([
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where(
                    fn ($query) => $query->where('branch_id', $branchId)->where('is_active', true)
                ),
            ],
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'cash_register_shift_id' => ['nullable', 'integer', 'exists:cash_register_shifts,id'],
        ]);

        $activeShift = null;
        if ($user->isCashier()) {
            $activeShift = CashRegisterShift::query()
                ->where('user_id', $user->id)
                ->where('branch_id', $branchId)
                ->where('status', 'open')
                ->first();
        }

        $shiftId = isset($validated['cash_register_shift_id'])
            ? (int) $validated['cash_register_shift_id']
            : $activeShift?->id;

        $expense = $this->expenseService->recordExpense([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'cash_register_shift_id' => $shiftId,
            'expense_category_id' => (int) $validated['expense_category_id'],
            'account_id' => (int) ($validated['account_id'] ?? $defaultCashAccountId),
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'],
        ]);

        $formattedAmount = 'Rp '.number_format((float) $expense->amount, 0, ',', '.');
        $catName = $expense->expenseCategory?->name ?? 'Biaya';

        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Pengeluaran kas {$formattedAmount} untuk {$catName} berhasil dibukukan.",
                'data' => $expense->load(['expenseCategory.chartOfAccount', 'chartOfAccount', 'journalHeader.journalLines']),
            ], 201);
        }

        if ($request->input('return_to') === 'finance') {
            return redirect()
                ->route('backoffice.finance.dashboard', ['tab' => 'pengeluaran'])
                ->with('success', "Pengeluaran kas {$formattedAmount} untuk {$catName} berhasil dibukukan dengan jurnal akuntansi otomatis.");
        }

        if ($request->input('return_to') === 'pos') {
            return redirect()
                ->route('pos')
                ->with('success', "Pengeluaran kas {$formattedAmount} untuk {$catName} berhasil dicatat dari kas cabang.");
        }

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

        $this->authorize('view', $expense);

        return view('backoffice.expenses.show', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'expense' => $expense,
        ]);
    }
}
