<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Models\CashRegisterShift;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesReturnController extends Controller
{
    public function __construct(
        protected SalesReturnService $salesReturnService
    ) {}

    /**
     * Display a listing of sales returns.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $refundMethod = $request->input('refund_method');

        $query = SalesReturn::with(['sale', 'user', 'branch', 'chartOfAccount', 'journalHeader'])
            ->when($startDate, fn ($q) => $q->where('return_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('return_date', '<=', $endDate))
            ->when($refundMethod, fn ($q) => $q->where('refund_method', $refundMethod))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('sale', fn ($s) => $s->where('receipt_number', 'like', "%{$search}%"));
                });
            })
            ->latest('return_date')
            ->latest('id');

        $returns = $query->paginate(15)->withQueryString();

        // Metrics for summary cards
        $allFiltered = (clone $query)->get();
        $totalReturnsAmount = (float) $allFiltered->sum('total_amount');
        $totalCount = $allFiltered->count();
        $totalCash = (float) $allFiltered->whereIn('refund_method', ['cash', 'Cash', 'tunai'])->sum('total_amount');
        $totalTransfer = (float) $allFiltered->whereIn('refund_method', ['transfer', 'Transfer', 'bank'])->sum('total_amount');

        return view('backoffice.sales-returns.index', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'returns' => $returns,
            'totalReturnsAmount' => $totalReturnsAmount,
            'totalCount' => $totalCount,
            'totalCash' => $totalCash,
            'totalTransfer' => $totalTransfer,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'refundMethod' => $refundMethod,
        ]);
    }

    /**
     * Show the form for creating a new sales return.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Products for this branch
        $products = Product::orderBy('name')
            ->get(['id', 'name', 'sku', 'selling_price', 'purchase_price', 'stock']);

        // Cash / Bank accounts for refunding
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

        // Recent sales for optional receipt reference
        $recentSales = Sale::orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'receipt_number', 'created_at', 'total_amount']);

        // Check if cashier has active shift
        $activeShift = CashRegisterShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        return view('backoffice.sales-returns.create', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'products' => $products,
            'accounts' => $accounts,
            'recentSales' => $recentSales,
            'activeShift' => $activeShift,
            'todayDate' => now()->toDateString(),
        ]);
    }

    /**
     * Store a newly created sales return in storage.
     */
    public function store(StoreSalesReturnRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $salesReturn = $this->salesReturnService->processReturn(
            [
                'branch_id' => $user->branch_id,
                'customer_name' => $validated['customer_name'] ?? 'Pelanggan Umum',
                'sale_id' => $validated['sale_id'] ?? null,
                'return_date' => $validated['return_date'],
                'refund_method' => $validated['refund_method'],
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'reason' => $validated['reason'] ?? null,
            ],
            $validated['items'],
            $user
        );

        $formattedAmount = 'Rp '.number_format((float) $salesReturn->total_amount, 0, ',', '.');

        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Retur Penjualan {$salesReturn->reference_number} ({$formattedAmount}) berhasil dicatat.",
                'data' => $salesReturn->load(['items.product', 'chartOfAccount', 'journalHeader.journalLines']),
            ], 201);
        }

        return redirect()
            ->route('backoffice.sales-returns.index')
            ->with('success', "Retur Penjualan {$salesReturn->reference_number} ({$formattedAmount}) berhasil dicatat. Stok barang bertambah dan jurnal refund telah diposting otomatis.");
    }

    /**
     * Display the specified sales return detail.
     */
    public function show(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');

        $salesReturn = SalesReturn::with([
            'branch',
            'user',
            'sale',
            'chartOfAccount',
            'items.product',
            'journalHeader.journalLines.chartOfAccount',
        ])->findOrFail($id);

        return view('backoffice.sales-returns.show', [
            'currentUser' => $user,
            'isMaster' => $user->isMaster(),
            'salesReturn' => $salesReturn,
        ]);
    }
}
