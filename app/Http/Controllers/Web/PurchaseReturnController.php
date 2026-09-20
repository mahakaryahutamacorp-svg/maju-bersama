<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct(
        protected PurchaseReturnService $returnService
    ) {}

    /**
     * Display a listing of purchase returns.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status');

        $query = PurchaseReturn::with(['supplier', 'branch', 'goodsReceipt', 'journalHeader'])
            ->when($startDate, fn ($q) => $q->where('return_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('return_date', '<=', $endDate))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('return_date')
            ->latest('id');

        $returns = $query->paginate(15)->withQueryString();

        // Metrics for summary cards
        $allFiltered = (clone $query)->get();
        $totalReturnsAmount = (float) $allFiltered->sum('total_amount');
        $totalCount = $allFiltered->count();
        $completedCount = $allFiltered->where('status', 'completed')->count();

        return view('backoffice.purchase-returns.index', [
            'currentUser'        => $user,
            'isMaster'           => $user->isMaster(),
            'returns'            => $returns,
            'totalReturnsAmount' => $totalReturnsAmount,
            'totalCount'         => $totalCount,
            'completedCount'     => $completedCount,
            'search'             => $search,
            'startDate'          => $startDate,
            'endDate'            => $endDate,
            'status'             => $status,
        ]);
    }

    /**
     * Show the form for creating a new purchase return.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Active suppliers for branch
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        // Available goods receipts
        $goodsReceipts = GoodsReceipt::orderByDesc('date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Products for this branch
        $products = Product::orderBy('name')
            ->get(['id', 'name', 'sku', 'purchase_price', 'selling_price', 'stock']);

        return view('backoffice.purchase-returns.create', [
            'currentUser'   => $user,
            'isMaster'      => $user->isMaster(),
            'suppliers'     => $suppliers,
            'goodsReceipts' => $goodsReceipts,
            'products'      => $products,
            'todayDate'     => now()->toDateString(),
        ]);
    }

    /**
     * Store a newly created purchase return in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'          => ['required', 'integer', 'exists:suppliers,id'],
            'goods_receipt_id'     => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'return_date'          => ['required', 'date'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.unit_price'   => ['required', 'numeric', 'min:0'],
        ]);

        $purchaseReturn = $this->returnService->processReturn(
            [
                'branch_id'        => $request->user()->branch_id,
                'supplier_id'      => $validated['supplier_id'],
                'goods_receipt_id' => $validated['goods_receipt_id'] ?? null,
                'return_date'      => $validated['return_date'],
                'notes'            => $validated['notes'] ?? null,
            ],
            $validated['items'],
            $request->user()
        );

        $formattedAmount = 'Rp ' . number_format((float) $purchaseReturn->total_amount, 0, ',', '.');
        $supplierName = $purchaseReturn->supplier?->name ?? 'Supplier';

        return redirect()
            ->route('backoffice.purchase-returns.index')
            ->with('success', "Retur Pembelian {$purchaseReturn->reference_number} ({$formattedAmount}) ke {$supplierName} berhasil dicatat. Stok gudang dan hutang dagang telah dikoreksi otomatis.");
    }

    /**
     * Display the specified purchase return detail.
     */
    public function show(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');

        $purchaseReturn = PurchaseReturn::with([
            'supplier',
            'branch',
            'goodsReceipt',
            'items.product',
            'journalHeader.journalLines.chartOfAccount',
        ])->findOrFail($id);

        return view('backoffice.purchase-returns.show', [
            'currentUser'    => $user,
            'isMaster'       => $user->isMaster(),
            'purchaseReturn' => $purchaseReturn,
        ]);
    }
}
