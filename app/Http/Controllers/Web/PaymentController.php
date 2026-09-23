<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\ARAPPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected ARAPPaymentService $arapPaymentService
    ) {}

    /**
     * Tampilkan riwayat pembayaran Hutang & Piutang (AR/AP).
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $type = $request->query('type');
        $search = $request->query('search');

        $query = Payment::with([
            'branch',
            'supplier',
            'account',
            'journalHeader.journalLines',
            'allocations.sale',
            'allocations.purchaseOrder',
        ])
            ->when($type, fn ($q) => $q->where('type', strtoupper($type)))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->latest('payment_date')
            ->latest('id');

        $payments = $query->paginate(15)->withQueryString();

        return view('backoffice.payments.index', [
            'currentUser' => $user,
            'isMaster'    => $user->isMaster(),
            'payments'    => $payments,
            'activeType'  => $type,
            'search'      => $search,
        ]);
    }

    /**
     * Formulir Penerimaan Pembayaran Piutang (AR).
     */
    public function createAR(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Akun Kas & Bank penerima dana
        $cashBankAccounts = ChartOfAccount::where('type', 'asset')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('name', 'like', '%kas%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->orderBy('code')
            ->get();

        // Faktur penjualan yang belum lunas (UNPAID / PARTIAL)
        $unpaidSales = Sale::where('payment_status', '!=', 'PAID')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('backoffice.payments.create-ar', [
            'currentUser'      => $user,
            'isMaster'         => $user->isMaster(),
            'cashBankAccounts' => $cashBankAccounts,
            'unpaidSales'      => $unpaidSales,
            'todayDate'        => now()->toDateString(),
        ]);
    }

    /**
     * Simpan Penerimaan Pembayaran Piutang (AR).
     */
    public function storeAR(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id'       => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'payment_date'     => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'allocations'      => ['required', 'array', 'min:1'],
            'allocations.*.sale_id'          => ['required', 'integer', 'exists:sales,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'min:0.01'],
        ], [
            'amount.min'          => 'Nominal pembayaran piutang harus lebih besar dari 0.',
            'allocations.required'=> 'Pilih minimal satu faktur penjualan yang akan dialokasikan pembayarannya.',
        ]);

        $payment = $this->arapPaymentService->processARPayment([
            'branch_id'        => $request->user()->branch_id,
            'account_id'       => (int) $validated['account_id'],
            'amount'           => $validated['amount'],
            'payment_date'     => $validated['payment_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ], $validated['allocations'], $request->user());

        $formattedAmount = 'Rp ' . number_format((float) $payment->amount, 0, ',', '.');
        return redirect()
            ->route('backoffice.payments.index')
            ->with('success', "Penerimaan pembayaran piutang senilai {$formattedAmount} berhasil dibukukan dengan ref: {$payment->reference_number}.");
    }

    /**
     * Formulir Pelunasan Pembayaran Hutang Supplier (AP).
     */
    public function createAP(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Akun Kas & Bank pengirim dana
        $cashBankAccounts = ChartOfAccount::where('type', 'asset')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '11%')
                  ->orWhere('name', 'like', '%kas%')
                  ->orWhere('name', 'like', '%bank%');
            })
            ->orderBy('code')
            ->get();

        // Data Supplier aktif
        $suppliers = Supplier::orderBy('name')->get();

        // Purchase Order yang belum lunas
        $supplierId = $request->query('supplier_id');
        $unpaidPOs = PurchaseOrder::where('payment_status', '!=', 'PAID')
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->with('supplier')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('backoffice.payments.create-ap', [
            'currentUser'      => $user,
            'isMaster'         => $user->isMaster(),
            'cashBankAccounts' => $cashBankAccounts,
            'suppliers'        => $suppliers,
            'unpaidPOs'        => $unpaidPOs,
            'selectedSupplierId' => $supplierId,
            'todayDate'        => now()->toDateString(),
        ]);
    }

    /**
     * Simpan Pelunasan Pembayaran Hutang Supplier (AP).
     */
    public function storeAP(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'      => ['nullable', 'integer', 'exists:suppliers,id'],
            'account_id'       => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'payment_date'     => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'allocations'      => ['required', 'array', 'min:1'],
            'allocations.*.purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'allocations.*.allocated_amount'  => ['required', 'numeric', 'min:0.01'],
        ], [
            'amount.min'          => 'Nominal pembayaran hutang harus lebih besar dari 0.',
            'allocations.required'=> 'Pilih minimal satu Purchase Order yang akan dialokasikan pembayarannya.',
        ]);

        $payment = $this->arapPaymentService->processAPPayment([
            'branch_id'        => $request->user()->branch_id,
            'supplier_id'      => $validated['supplier_id'] ?? null,
            'account_id'       => (int) $validated['account_id'],
            'amount'           => $validated['amount'],
            'payment_date'     => $validated['payment_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ], $validated['allocations'], $request->user());

        $formattedAmount = 'Rp ' . number_format((float) $payment->amount, 0, ',', '.');
        return redirect()
            ->route('backoffice.payments.index')
            ->with('success', "Pelunasan pembayaran hutang senilai {$formattedAmount} berhasil dibukukan dengan ref: {$payment->reference_number}.");
    }
}
