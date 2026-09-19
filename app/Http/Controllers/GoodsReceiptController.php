<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        protected GoodsReceiptService $goodsReceiptService
    ) {}

    /**
     * Display a listing of goods receipts history.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $paymentType = $request->input('payment_type');
        $search = $request->input('search');

        $query = GoodsReceipt::with(['branch', 'items.product', 'journalHeader'])
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('date', '<=', $endDate))
            ->when($paymentType, fn ($q) => $q->where('payment_type', $paymentType))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%");
                });
            })
            ->latest('date')
            ->latest('id');

        $receipts = $query->paginate(15)->withQueryString();

        // Metrics for summary cards (based on current filtered query or all)
        $allFiltered = (clone $query)->get();
        $totalReceipts = $allFiltered->count();
        $totalAmount = (float) $allFiltered->sum('total_amount');
        $totalCash = (float) $allFiltered->where('payment_type', 'cash')->sum('total_amount');
        $totalCredit = (float) $allFiltered->where('payment_type', 'credit')->sum('total_amount');

        return view('purchases.goods-receipts.index', [
            'currentUser' => $user,
            'receipts' => $receipts,
            'totalReceipts' => $totalReceipts,
            'totalAmount' => $totalAmount,
            'totalCash' => $totalCash,
            'totalCredit' => $totalCredit,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'paymentType' => $paymentType,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new goods receipt.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Resolve central branch
        $centralBranch = Branch::whereNull('parent_id')->first()
            ?? Branch::where('code', 'PUSAT')->first()
            ?? Branch::first();

        // Fetch products available at central branch (or fallback to all products if central has none)
        $products = Product::withoutGlobalScopes()
            ->when($centralBranch, fn ($q) => $q->where('branch_id', $centralBranch->id))
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            $products = Product::withoutGlobalScopes()->orderBy('name')->get();
        }

        $productsData = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'purchase_price' => (float) $p->purchase_price,
            'stock' => (int) $p->availableQuantity(),
        ])->values();

        // Fetch active POs (pending or partial) with items and supplier
        $activePOs = PurchaseOrder::withoutGlobalScopes()
            ->with(['supplier', 'items.product'])
            ->whereIn('status', ['pending', 'partial'])
            ->when($centralBranch, fn ($q) => $q->where('branch_id', $centralBranch->id))
            ->latest('order_date')
            ->latest('id')
            ->get();

        if ($activePOs->isEmpty()) {
            $activePOs = PurchaseOrder::withoutGlobalScopes()
                ->with(['supplier', 'items.product'])
                ->whereIn('status', ['pending', 'partial'])
                ->latest('order_date')
                ->latest('id')
                ->get();
        }

        $activePOsData = $activePOs->map(function (PurchaseOrder $po) {
            return [
                'id'               => $po->id,
                'reference_number' => $po->reference_number,
                'supplier_name'    => $po->supplier?->name ?? '',
                'order_date'       => $po->order_date ? $po->order_date->format('d/m/Y') : '',
                'status'           => $po->status,
                'items'            => $po->items->map(function ($item) {
                    $remaining = max(0, (int) $item->quantity - (int) $item->received_quantity);
                    return [
                        'product_id'         => $item->product_id,
                        'product_name'       => $item->product?->name ?? 'Produk #' . $item->product_id,
                        'sku'                => $item->product?->sku ?? '',
                        'quantity'           => (int) $item->quantity,
                        'received_quantity'  => (int) $item->received_quantity,
                        'remaining_quantity' => $remaining,
                        'unit_price'         => (float) $item->unit_price,
                    ];
                })->filter(fn ($item) => $item['remaining_quantity'] > 0)->values(),
            ];
        })->values();

        return view('purchases.goods-receipts.create', [
            'currentUser'   => $user,
            'centralBranch' => $centralBranch,
            'products'      => $productsData,
            'activePOs'     => $activePOs,
            'activePOsData' => $activePOsData,
            'todayDate'     => now()->toDateString(),
        ]);
    }

    /**
     * Store a newly created goods receipt in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'payment_type' => ['required', 'in:cash,credit'],
            'reference_number' => ['nullable', 'string', 'max:100', 'unique:goods_receipts,reference_number'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $receipt = $this->goodsReceiptService->processReceipt($validated, $request->user());

        return redirect()
            ->route('purchases.goods-receipts.show', $receipt->id)
            ->with('success', "Penerimaan barang {$receipt->reference_number} berhasil diproses. Stok gudang bertambah dan jurnal akuntansi telah dicatat otomatis.");
    }

    /**
     * Display the specified goods receipt details and accounting impact.
     */
    public function show(int $id, Request $request): View
    {
        $user = $request->user()->load('branch');

        $receipt = GoodsReceipt::with([
            'branch',
            'items.product',
            'journalHeader.journalLines.chartOfAccount',
        ])->findOrFail($id);

        return view('purchases.goods-receipts.show', [
            'currentUser' => $user,
            'receipt' => $receipt,
        ]);
    }
}
