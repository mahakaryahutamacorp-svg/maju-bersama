<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegisterShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\ExpenseCategory;
use App\Services\BranchCashAccountService;
use App\Services\BranchCashDrawerService;
use App\Services\BranchExpenseSetupService;
use App\Services\CashRegisterShiftService;
use App\Services\ExpenseService;
use App\Services\SalePostingService;
use App\Support\DecimalQuantity;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected CashRegisterShiftService $shiftService,
        protected BranchCashDrawerService $drawers,
        protected BranchExpenseSetupService $branchExpenseSetup,
        protected BranchCashAccountService $branchCashAccounts,
        protected ExpenseService $expenseService,
    ) {}

    /**
     * Record a POS sale transaction directly from the POS screen / web route.
     */
    public function store(Request $request, SalePostingService $salePostingService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => DecimalQuantity::rules(),
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $idempotencyCacheKey = ! empty($validated['idempotency_key'])
            ? "pos-checkout:{$user->id}:{$validated['idempotency_key']}"
            : null;

        $sale = null;
        if ($idempotencyCacheKey !== null) {
            $sale = Cache::lock("{$idempotencyCacheKey}:lock", 30)->block(15, function () use ($idempotencyCacheKey, $validated, $user, $salePostingService) {
                $existingSaleId = Cache::get($idempotencyCacheKey);
                if ($existingSaleId) {
                    return Sale::withoutGlobalScopes()->with('items.product')->find($existingSaleId);
                }

                $sale = $salePostingService->post($validated, $user);
                Cache::put($idempotencyCacheKey, $sale->id, now()->addDay());

                return $sale;
            });
        }

        $sale ??= $salePostingService->post($validated, $user);

        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            $activeShift = $this->shiftService->getActiveShiftForUser($user->id);

            return response()->json([
                'message' => 'Checkout completed successfully.',
                'status' => 'success',
                'receipt_number' => $sale->receipt_number,
                'sale' => $sale,
                'expected_balance' => $activeShift ? $this->shiftService->calculateExpectedBalance($activeShift) : null,
            ], 201);
        }

        return redirect()->route('pos.receipt', $sale->receipt_number);
    }

    /**
     * Saldo kas laci yang diharapkan untuk shift aktif kasir, dihitung ulang saat modal tutup shift dibuka.
     */
    public function expectedShiftBalance(Request $request): JsonResponse
    {
        $activeShift = $this->shiftService->getActiveShiftForUser($request->user()->id);

        return response()->json([
            'expected_balance' => $activeShift ? $this->shiftService->calculateExpectedBalance($activeShift) : null,
        ]);
    }

    /**
     * Display the POS cashier screen.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $branchId = $this->operatingBranchId($request, $user);

        if ($branchId === null) {
            return view('pos.select-branch', [
                'currentUser' => $user,
                'branches' => $user->isMaster()
                    ? Branch::query()->orderBy('name')->get()
                    : collect(),
                'needsBranchAssignment' => ! $user->isMaster(),
                'invalidBranch' => $request->filled('branch_id'),
            ]);
        }

        $operatingBranch = Branch::query()->findOrFail($branchId);

        // Periksa apakah kasir memiliki shift yang sedang aktif
        $activeShift = CashRegisterShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->with(['cashRegister', 'sales'])
            ->latest('opened_at')
            ->first();

        $registers = $this->drawers->ensure($branchId);
        $openShifts = CashRegisterShift::query()
            ->whereIn('cash_register_id', $registers->pluck('id'))
            ->where('status', 'open')
            ->with('user:id,name')
            ->get()
            ->keyBy('cash_register_id');

        // Kalkulasi dinamis expected closing balance untuk modal tutup shift
        $expectedBalance = $activeShift
            ? $this->shiftService->calculateExpectedBalance($activeShift)
            : 0;

        $productsQuery = Product::withoutGlobalScopes()
            ->whereNull('deleted_at');

        if ($branchId) {
            $productsQuery->where('branch_id', $branchId);
        }

        $products = $productsQuery
            ->with(['category', 'productPrices'])
            ->orderBy('name')
            ->get();

        $skus = $products->pluck('sku')->filter()->unique()->values()->all();
        $otherStockMap = [];
        if (! empty($skus)) {
            $otherBranchesQuery = Product::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('stock', '>', 0)
                ->whereIn('sku', $skus)
                ->with('branch:id,name');

            if ($branchId) {
                $otherBranchesQuery->where('branch_id', '!=', $branchId);
            }

            $otherProducts = $otherBranchesQuery->get(['id', 'branch_id', 'sku', 'stock']);

            foreach ($otherProducts as $op) {
                $branchName = $op->branch?->name ?? 'Cabang #'.$op->branch_id;
                $otherStockMap[$op->sku][] = [
                    'branch_name' => $branchName,
                    'stock' => DecimalQuantity::present($op->stock),
                ];
            }
        }

        $products = $products->map(function (Product $product) use ($otherStockMap) {
            $basePrice = (float) $product->selling_price;
            $product->base_price = $basePrice;
            $product->original_selling_price = $basePrice;
            $product->selling_price = $basePrice;
            $product->price = $basePrice;
            $product->other_branch_stock = $otherStockMap[$product->sku] ?? [];

            return $product;
        });

        $catalog = $operatingBranch->productCatalog();

        $categories = Category::withCount(['products' => function ($query) use ($branchId) {
            $query->withoutGlobalScopes()
                ->whereNull('deleted_at');
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        }])
            ->forCatalog($catalog)
            ->orderBy('name')
            ->get();

        $customersQuery = Customer::withoutGlobalScopes()
            ->with(['customerGroup:id,name', 'branch:id,name']);

        if (! $user->isMaster()) {
            $customersQuery->where('branch_id', $branchId);
        }

        $customers = $customersQuery->orderBy('name')->get();

        $this->branchExpenseSetup->ensureForBranch($branchId);
        $expenseCategories = ExpenseCategory::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->with('chartOfAccount')
            ->orderBy('name')
            ->get();
        $branchCashAccount = $this->branchCashAccounts->defaultForBranch($branchId);

        $token = $user->createToken('pos_preview')->plainTextToken;

        $viewName = view()->exists('pos.index') ? 'pos.index' : 'pos';

        return view($viewName, [
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'customerGroups' => CustomerGroup::orderBy('id')->get(),
            'previewToken' => $token,
            'currentUser' => $user,
            'operatingBranch' => $operatingBranch,
            'activeShift' => $activeShift,
            'hasActiveShift' => $activeShift !== null,
            'registers' => $registers,
            'openShifts' => $openShifts,
            'expectedBalance' => $expectedBalance,
            'expenseCategories' => $expenseCategories,
            'branchCashAccount' => $branchCashAccount,
        ]);
    }

    /**
     * Catat pengeluaran operasional dari layar POS; dana diambil dari kas cabang (default akun Kas).
     */
    public function storeExpense(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', \App\Models\Expense::class);

        $user = $request->user();
        $branchId = $this->operatingBranchId($request, $user);

        if ($branchId === null) {
            return redirect()->route('pos')->with('error', 'Cabang operasional belum dipilih.');
        }

        $this->branchExpenseSetup->ensureForBranch($branchId);
        $defaultCashAccountId = $this->branchCashAccounts->defaultForBranch($branchId)->id;

        $validated = $request->validate([
            'expense_category_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('expense_categories', 'id')->where(
                    fn ($query) => $query->where('branch_id', $branchId)->where('is_active', true)
                ),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $activeShift = CashRegisterShift::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->first();

        $expense = $this->expenseService->recordExpense([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'cash_register_shift_id' => $activeShift?->id,
            'expense_category_id' => (int) $validated['expense_category_id'],
            'account_id' => $defaultCashAccountId,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'] ?? now()->toDateString(),
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'],
        ]);

        $formattedAmount = 'Rp '.number_format((float) $expense->amount, 0, ',', '.');
        $catName = $expense->expenseCategory?->name ?? 'Biaya';

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Pengeluaran kas {$formattedAmount} untuk {$catName} berhasil dicatat.",
                'expected_balance' => $activeShift
                    ? $this->shiftService->calculateExpectedBalance($activeShift->fresh())
                    : null,
            ], 201);
        }

        return redirect()
            ->route('pos')
            ->with('success', "Pengeluaran kas {$formattedAmount} untuk {$catName} berhasil dicatat dari kas cabang.");
    }

    /**
     * Buka shift kasir baru dari modal pemblokir POS.
     */
    public function openShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_register_id' => ['required', 'integer', 'exists:cash_registers,id'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $shift = $this->shiftService->openShift(
            $validated['cash_register_id'],
            $request->user()->id,
            $validated['opening_balance'],
            $validated['notes'] ?? null
        );

        $formatted = 'Rp '.number_format($shift->opening_balance, 0, ',', '.');

        return redirect()
            ->route('pos')
            ->with('success', "Shift kasir berhasil dibuka dengan modal awal {$formatted}.");
    }

    /**
     * Tutup shift kasir dari tombol navigasi POS.
     */
    public function closeShift(Request $request): RedirectResponse
    {
        $activeShift = CashRegisterShift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if (! $activeShift) {
            return redirect()
                ->route('pos')
                ->with('error', 'Tidak ada sesi shift kasir aktif yang dapat ditutup.');
        }

        $validated = $request->validate([
            'actual_closing_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = $this->shiftService->closeShift(
            $activeShift,
            $validated['actual_closing_balance'],
            $validated['notes'] ?? null
        );

        $actualFormatted = 'Rp '.number_format($shift->actual_closing_balance, 0, ',', '.');
        $diffFormatted = 'Rp '.number_format(abs($shift->difference), 0, ',', '.');
        $diffLabel = $shift->difference > 0 ? "Surplus {$diffFormatted}" : ($shift->difference < 0 ? "Defisit {$diffFormatted}" : 'Seimbang (Rp 0)');

        return redirect()
            ->route('pos')
            ->with('success', "Shift kasir berhasil ditutup. Total fisik: {$actualFormatted} ({$diffLabel}).");
    }

    /**
     * Display thermal receipt for a completed sale.
     */
    public function receipt(Request $request, string $receiptNumber): View
    {
        $sale = Sale::with([
            'branch',
            'creator',
            'items.product',
            'customerGroup',
            // Master bisa menjual ke pelanggan cabang lain, jadi scope cabang dilewati.
            'customer' => fn ($query) => $query->withoutGlobalScopes(),
        ])
            ->where('receipt_number', $receiptNumber)
            ->firstOrFail();

        $cashTendered = $request->query('cash');
        $changeDue = $request->query('change');

        $isTempo = in_array(strtolower((string) $sale->payment_method), ['tempo', 'piutang', 'kredit'], true);
        $downPayment = null;
        $remainingReceivable = null;
        if ($isTempo) {
            $settled = Money::of($sale->paymentAllocations()->sum('allocated_amount'));
            $downPayment = bcsub(Money::of($sale->paid_amount ?? 0), $settled, 2);
            if (bccomp($downPayment, '0.00', 2) === -1) {
                $downPayment = '0.00';
            }
            $remainingReceivable = bcsub(Money::of($sale->total_amount), $downPayment, 2);
        }

        return view('pos.receipt', [
            'sale' => $sale,
            'cashTendered' => $cashTendered ? (float) $cashTendered : null,
            'changeDue' => $changeDue ? (float) $changeDue : null,
            'isTempo' => $isTempo,
            'downPayment' => $downPayment,
            'remainingReceivable' => $remainingReceivable,
        ]);
    }

    /**
     * Daftarkan pelanggan baru secara cepat langsung dari layar POS Kasir.
     */
    public function storeCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_group_id' => ['required', 'integer', 'exists:customer_groups,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $requestedBranchId = (int) $request->input('branch_id');
        $branchId = $user->branch_id;

        if ($user->isMaster() && $requestedBranchId > 0 && Branch::query()->whereKey($requestedBranchId)->exists()) {
            $branchId = $requestedBranchId;
        }

        $branchId = $branchId ?: Branch::whereNull('parent_id')->value('id') ?: Branch::value('id');

        $customer = Customer::create([
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'customer_group_id' => $validated['customer_group_id'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        $customer->load(['customerGroup', 'branch']);

        return response()->json([
            'status' => 'success',
            'message' => "Pelanggan '{$customer->name}' berhasil didaftarkan.",
            'customer' => $customer,
        ], 201);
    }

    /**
     * Kasir selalu di cabangnya. Master tanpa cabang harus memilih cabang kasir.
     */
    private function operatingBranchId(Request $request, User $user): ?int
    {
        if ($user->isMaster() && $request->filled('branch_id')) {
            $branchId = (int) $request->query('branch_id');

            return Branch::query()->whereKey($branchId)->exists() ? $branchId : null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }
}
