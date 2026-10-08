<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierDebtPaymentRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Supplier;
use App\Services\SupplierDebtPaymentService;
use App\Services\SupplierLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    private const HUB_TABS = ['riwayat_belanja', 'riwayat_pembayaran', 'riwayat_retur'];

    public function __construct(
        protected SupplierLedgerService $ledgerService,
        protected SupplierDebtPaymentService $debtPaymentService
    ) {}

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $filterBranch = $request->input('branch_id');
        $status = $request->input('status');

        $query = Supplier::with('branch')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status !== null && $status !== '', function ($q) use ($status) {
                $q->where('is_active', (bool) $status);
            })
            ->when($user->isMaster() && $filterBranch, function ($q) use ($filterBranch) {
                $q->where('branch_id', $filterBranch);
            })
            ->latest('id');

        $suppliers = $query->paginate(15)->withQueryString();

        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $suppliers->items(),
                'total' => $suppliers->total(),
            ]);
        }

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.suppliers.index', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'suppliers' => $suppliers,
            'branches' => $branches,
            'search' => $search,
            'filterBranch' => $filterBranch,
            'status' => $status,
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.suppliers.create', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'branches' => $branches,
        ]);
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        // Automatically assign branch_id from authenticated user, or allow master override
        $branchId = $user->branch_id;
        if ($user->isMaster() && ! empty($validated['branch_id'])) {
            $branchId = (int) $validated['branch_id'];
        }

        if (! $branchId) {
            $branchId = Branch::value('id');
        }

        Supplier::create([
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('backoffice.suppliers.index')
            ->with('success', "Supplier '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Buku Pemasok: profil, sisa hutang, riwayat belanja/pembayaran/retur dalam satu layar.
     */
    public function show(Request $request, Supplier $supplier): View
    {
        $user = $request->user()->load('branch');
        $tab = $request->input('tab');

        return view('backoffice.suppliers.show', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'supplier' => $supplier->load('branch'),
            'ledger' => $this->ledgerService->build($supplier),
            'cashBankAccounts' => $this->cashBankAccounts(),
            'tab' => in_array($tab, self::HUB_TABS, true) ? $tab : 'riwayat_belanja',
            'todayDate' => now()->toDateString(),
        ]);
    }

    /**
     * Bayar Hutang (boleh cicil) dari laci Buku Pemasok, dipotong FIFO ke nota terlama.
     */
    public function storePayment(StoreSupplierDebtPaymentRequest $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $payment = $this->debtPaymentService->pay($supplier, $validated, $request->user());

        $formattedAmount = 'Rp '.number_format((float) $payment->amount, 0, ',', '.');
        $message = "Pembayaran {$formattedAmount} ke {$supplier->name} berhasil dicatat.";

        if ($request->expectsJson()) {
            $ledger = $this->ledgerService->build($supplier);

            return response()->json([
                'message' => $message,
                'total_debt' => (float) $ledger['total_debt'],
                'total_paid' => (float) $ledger['total_paid'],
                'invoices' => $ledger['invoices']->mapWithKeys(fn (array $row) => [
                    $row['key'] => [
                        'paid' => (float) $row['paid'],
                        'outstanding' => (float) $row['outstanding'],
                        'status' => $row['status'],
                    ],
                ]),
                'open_invoices' => $this->openInvoicesPayload($ledger['open_invoices']),
                'payment' => [
                    'date_label' => $payment->payment_date?->isoFormat('D MMM Y'),
                    'description' => $this->ledgerService->paymentDescription(
                        $payment->account?->name,
                        $payment->notes,
                        $payment->allocations->count()
                    ),
                    'amount' => (float) $payment->amount,
                ],
            ], 201);
        }

        return redirect()
            ->route('backoffice.suppliers.show', [
                'supplier' => $supplier,
                'tab' => $validated['tab'] ?? 'riwayat_pembayaran',
            ])
            ->with('success', $message);
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Request $request, Supplier $supplier): View
    {
        $user = $request->user()->load('branch');

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.suppliers.edit', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'supplier' => $supplier,
            'branches' => $branches,
        ]);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($user->isMaster() && ! empty($validated['branch_id'])) {
            $payload['branch_id'] = (int) $validated['branch_id'];
        }

        $supplier->update($payload);

        return redirect()
            ->route('backoffice.suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' berhasil diperbarui.");
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return redirect()
                ->route('backoffice.suppliers.index')
                ->with('error', "Supplier '{$supplier->name}' tidak dapat dihapus karena telah terikat dengan Purchase Order.");
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()
            ->route('backoffice.suppliers.index')
            ->with('success', "Supplier '{$name}' berhasil dihapus.");
    }

    private function cashBankAccounts()
    {
        $accounts = ChartOfAccount::query()
            ->where('type', 'asset')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                    ->orWhere('name', 'like', '%kas%')
                    ->orWhere('name', 'like', '%bank%');
            })
            ->where('code', 'not like', '113%')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return $accounts->isNotEmpty()
            ? $accounts
            : ChartOfAccount::query()->where('type', 'asset')->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * @return array<int, array{id: int, label: string, outstanding: float}>
     */
    private function openInvoicesPayload($openInvoices): array
    {
        return $openInvoices->map(fn (array $row) => [
            'id' => $row['key'],
            'label' => $row['description'].' ('.($row['date']?->isoFormat('D MMM Y') ?? '-').')',
            'outstanding' => (float) $row['outstanding'],
        ])->values()->all();
    }
}
