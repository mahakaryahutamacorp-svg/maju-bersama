<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $search = $request->input('search');
        $status = $request->input('status');
        $filterBranch = $request->input('branch_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = PurchaseOrder::with(['branch', 'supplier', 'items.product'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($startDate, fn ($q) => $q->where('order_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('order_date', '<=', $endDate))
            ->when($user->isMaster() && $filterBranch, fn ($q) => $q->where('branch_id', $filterBranch))
            ->latest('order_date')
            ->latest('id');

        $purchaseOrders = $query->paginate(15)->withQueryString();

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        // Metrics for summary cards
        $allPOs = (clone $query)->get();
        $metrics = [
            'total'     => $allPOs->count(),
            'pending'   => $allPOs->where('status', 'pending')->count(),
            'completed' => $allPOs->where('status', 'completed')->count(),
            'amount'    => (float) $allPOs->sum('total_amount'),
        ];

        return view('backoffice.purchase-orders.index', [
            'currentUser'    => $user,
            'isMaster'       => $user->isMaster(),
            'purchaseOrders' => $purchaseOrders,
            'branches'       => $branches,
            'metrics'        => $metrics,
            'search'         => $search,
            'status'         => $status,
            'filterBranch'   => $filterBranch,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
        ]);
    }

    /**
     * Show the form for creating a new purchase order.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');

        // Fetch active suppliers (scoped to user's branch via HasBranchScope)
        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'contact_person', 'phone']);

        // Fetch active products
        $products = Product::orderBy('name')
            ->get(['id', 'sku', 'name', 'purchase_price', 'stock']);

        $productsData = $products->map(fn (Product $p) => [
            'id'             => $p->id,
            'sku'            => $p->sku,
            'name'           => $p->name,
            'purchase_price' => (float) $p->purchase_price,
            'stock'          => (int) $p->stock,
        ]);

        $branches = $user->isMaster()
            ? Branch::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        return view('backoffice.purchase-orders.create', [
            'currentUser'  => $user,
            'isMaster'     => $user->isMaster(),
            'suppliers'    => $suppliers,
            'products'     => $productsData,
            'branches'     => $branches,
            'today'        => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a newly created purchase order in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'supplier_id'         => ['required', 'exists:suppliers,id'],
            'order_date'          => ['required', 'date'],
            'expected_date'       => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'branch_id'           => ['nullable', 'exists:branches,id'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ], [
            'items.required'      => 'Minimal harus memasukkan satu item produk dalam Purchase Order.',
            'items.min'           => 'Minimal harus memasukkan satu item produk dalam Purchase Order.',
            'items.*.product_id.required' => 'Pilih produk untuk setiap baris pemesanan.',
            'items.*.quantity.min'        => 'Kuantitas minimal adalah 1.',
        ]);

        $po = DB::transaction(function () use ($user, $validated) {
            // Determine branch_id
            $branchId = $user->branch_id;
            if ($user->isMaster() && ! empty($validated['branch_id'])) {
                $branchId = (int) $validated['branch_id'];
            }

            if (! $branchId) {
                // Fallback to supplier branch or first available branch
                $supplier = Supplier::withoutGlobalScopes()->find($validated['supplier_id']);
                $branchId = $supplier?->branch_id ?? Branch::value('id');
            }

            // Generate unique reference number: PO-YYYYMMDD-XXXX
            $datePrefix = now()->format('Ymd');
            $countToday = PurchaseOrder::withoutGlobalScopes()
                ->whereDate('created_at', today())
                ->count() + 1;
            $referenceNumber = 'PO-' . $datePrefix . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            // Double check uniqueness
            while (PurchaseOrder::withoutGlobalScopes()->where('reference_number', $referenceNumber)->exists()) {
                $referenceNumber = 'PO-' . $datePrefix . '-' . strtoupper(Str::random(4));
            }

            // Calculate total amount from items
            $totalAmount = 0;
            $itemsData = [];
            foreach ($validated['items'] as $item) {
                $qty = (int) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $subtotal = round($qty * $unitPrice, 2);
                $totalAmount += $subtotal;

                $itemsData[] = [
                    'product_id'        => $item['product_id'],
                    'quantity'          => $qty,
                    'received_quantity' => 0,
                    'unit_price'        => $unitPrice,
                    'subtotal'          => $subtotal,
                ];
            }

            // Create Main Purchase Order record with default status 'pending'
            $purchaseOrder = PurchaseOrder::create([
                'branch_id'        => $branchId,
                'supplier_id'      => $validated['supplier_id'],
                'reference_number' => $referenceNumber,
                'order_date'       => $validated['order_date'],
                'expected_date'    => $validated['expected_date'] ?? null,
                'status'           => 'pending',
                'total_amount'     => $totalAmount,
                'notes'            => $validated['notes'] ?? null,
            ]);

            // Create Purchase Order Items
            foreach ($itemsData as $itemData) {
                $purchaseOrder->items()->create($itemData);
            }

            return $purchaseOrder;
        });

        return redirect()
            ->route('backoffice.purchase-orders.show', $po->id)
            ->with('success', "Purchase Order '{$po->reference_number}' berhasil diterbitkan dengan status 'Pending'.");
    }

    /**
     * Display the specified purchase order details and slip.
     */
    public function show(Request $request, int $id): View
    {
        $user = $request->user()->load('branch');

        $purchaseOrder = PurchaseOrder::with(['branch', 'supplier', 'items.product', 'goodsReceipts'])
            ->findOrFail($id);

        return view('backoffice.purchase-orders.show', [
            'currentUser'   => $user,
            'isMaster'      => $user->isMaster(),
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    /**
     * Remove the specified purchase order from storage (if draft or pending and no GR).
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->goodsReceipts()->exists()) {
            return redirect()
                ->route('backoffice.purchase-orders.index')
                ->with('error', "Purchase Order '{$purchaseOrder->reference_number}' tidak dapat dihapus karena telah memiliki riwayat Penerimaan Barang (Goods Receipt).");
        }

        $ref = $purchaseOrder->reference_number;
        $purchaseOrder->delete();

        return redirect()
            ->route('backoffice.purchase-orders.index')
            ->with('success', "Purchase Order '{$ref}' berhasil dihapus.");
    }
}
