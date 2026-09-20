<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $paymentService
    ) {}

    /**
     * Display a listing of supplier payments history.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $paymentMethod = $request->input('payment_method');

        $query = SupplierPayment::with(['supplier', 'chartOfAccount', 'user', 'branch', 'journalHeader'])
            ->when($startDate, fn ($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('payment_date', '<=', $endDate))
            ->when($paymentMethod, fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('payment_date')
            ->latest('id');

        $payments = $query->paginate(15)->withQueryString();

        // Metrics for summary cards
        $allFiltered = (clone $query)->get();
        $totalPayments = (float) $allFiltered->sum('amount');
        $totalCount = $allFiltered->count();
        $totalTransfer = (float) $allFiltered->whereIn('payment_method', ['Transfer', 'transfer'])->sum('amount');
        $totalCash = (float) $allFiltered->whereIn('payment_method', ['Cash', 'cash'])->sum('amount');

        return view('backoffice.supplier-payments.index', [
            'currentUser'    => $user,
            'isMaster'       => $user->isMaster(),
            'payments'       => $payments,
            'totalPayments'  => $totalPayments,
            'totalCount'     => $totalCount,
            'totalTransfer'  => $totalTransfer,
            'totalCash'      => $totalCash,
            'search'         => $search,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'paymentMethod'  => $paymentMethod,
        ]);
    }

    /**
     * Show the form for creating a new supplier payment.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Active suppliers
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        // Accounts filtered for Kas & Bank / liquid assets
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

        return view('backoffice.supplier-payments.create', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'suppliers'   => $suppliers,
            'accounts'    => $accounts,
            'todayDate'   => now()->toDateString(),
        ]);
    }

    /**
     * Store a newly created supplier payment in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'         => ['required', 'integer', 'exists:suppliers,id'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'payment_date'        => ['required', 'date'],
            'payment_method'      => ['required', 'string', 'in:Transfer,Cash,Giro,transfer,cash,giro'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $this->paymentService->processPayment($validated, $request->user());

        $formattedAmount = 'Rp ' . number_format((float) $payment->amount, 0, ',', '.');
        $supplierName = $payment->supplier?->name ?? 'Supplier';

        return redirect()
            ->route('backoffice.supplier-payments.index')
            ->with('success', "Pembayaran {$formattedAmount} ke {$supplierName} berhasil dicatat. Jurnal akuntansi telah diposting otomatis.");
    }

    /**
     * Display the specified supplier payment detail.
     */
    public function show(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');

        $payment = SupplierPayment::with([
            'supplier',
            'chartOfAccount',
            'user',
            'branch',
            'journalHeader.journalLines.chartOfAccount',
        ])->findOrFail($id);

        return view('backoffice.supplier-payments.show', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'payment'     => $payment,
        ]);
    }
}
