<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(
        protected StockAdjustmentService $stockAdjustmentService
    ) {}

    /**
     * Display a listing of stock adjustment documents.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = in_array($user->role, ['master', 'superadmin'], true);

        $branchId = $isMaster ? $request->input('branch_id') : $user->branch_id;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');

        $query = StockAdjustment::with(['branch', 'items.product', 'journal'])
            ->when(! $isMaster, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('date', '<=', $endDate))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->latest('date')
            ->latest('id');

        $adjustments = $query->paginate(15)->withQueryString();

        // Metrics for summary cards
        $allFiltered = (clone $query)->get();
        $totalAdjustments = $allFiltered->count();
        $totalLoss = (float) $allFiltered->sum('total_loss_value');
        $totalGain = (float) $allFiltered->sum('total_gain_value');
        $netImpact = $totalGain - $totalLoss;

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);

        return view('inventory.adjustments.index', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'adjustments' => $adjustments,
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'totalAdjustments' => $totalAdjustments,
            'totalLoss' => $totalLoss,
            'totalGain' => $totalGain,
            'netImpact' => $netImpact,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new stock adjustment.
     */
    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = in_array($user->role, ['master', 'superadmin'], true);

        // Resolve branch to perform opname on
        $selectedBranchId = $request->input('branch_id');
        if (! $isMaster || ! $selectedBranchId) {
            $currentBranch = $user->branch
                ?? Branch::whereNull('parent_id')->first()
                ?? Branch::where('code', 'PUSAT')->first()
                ?? Branch::first();
        } else {
            $currentBranch = Branch::find($selectedBranchId) ?? $user->branch;
        }

        // Fetch products applicable to this branch
        $products = Product::withoutGlobalScopes()
            ->when($currentBranch, fn ($q) => $q->where('branch_id', $currentBranch->id))
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            $products = Product::withoutGlobalScopes()->orderBy('name')->get();
        }

        // Prepare products JSON payload for Alpine.js
        $productsData = $products->map(function (Product $p) use ($currentBranch) {
            $inventory = Inventory::withoutGlobalScopes()
                ->where('branch_id', $currentBranch->id)
                ->where('product_id', $p->id)
                ->first();

            $onHandQty = $inventory?->quantity ?? (int) $p->stock;

            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'purchase_price' => (float) $p->purchase_price,
                'selling_price' => (float) $p->selling_price,
                'stock' => (int) $onHandQty,
            ];
        })->values();

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$currentBranch]);

        return view('inventory.adjustments.create', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'currentBranch' => $currentBranch,
            'branches' => $branches,
            'products' => $productsData,
        ]);
    }

    /**
     * Store a newly created stock adjustment.
     */
    public function store(Request $request): RedirectResponse
    {
        $rawLines = $request->input('lines') ?? $request->input('items') ?? [];
        if (! empty($rawLines) && is_array($rawLines)) {
            $mapped = array_map(function ($item) {
                if (isset($item['system_qty']) && ! isset($item['expected_qty'])) {
                    $item['expected_qty'] = $item['system_qty'];
                }
                return $item;
            }, $rawLines);
            $request->merge(['items' => $mapped]);
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date' => ['nullable', 'date'],
            'adjustment_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.actual_qty' => ['required', 'integer', 'min:0'],
            'items.*.expected_qty' => ['nullable', 'integer'],
            'items.*.system_qty' => ['nullable', 'integer'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Minimal satu produk wajib dimasukkan untuk penyesuaian stok.',
            'items.min' => 'Minimal satu produk wajib dimasukkan untuk penyesuaian stok.',
            'items.*.actual_qty.required' => 'Stok fisik (actual qty) wajib diisi.',
            'items.*.actual_qty.min' => 'Stok fisik tidak boleh kurang dari 0.',
        ]);

        $user = $request->user();

        // If regular user, enforce their own branch
        if (! in_array($user->role, ['master', 'superadmin'], true)) {
            $validated['branch_id'] = $user->branch_id;
        }

        $adjustment = $this->stockAdjustmentService->processAdjustment($validated, $validated['items'], $user);

        return redirect()
            ->route('inventory.adjustments.show', $adjustment->id)
            ->with('success', "Penyesuaian stok #{$adjustment->reference_number} berhasil diproses dan jurnal telah dibukukan!");
    }

    /**
     * Display the specified stock adjustment slip.
     */
    public function show(Request $request, int|string $id): View
    {
        $user = $request->user()->load('branch');
        $isMaster = in_array($user->role, ['master', 'superadmin'], true);

        $adjustment = StockAdjustment::with([
            'branch',
            'items.product',
            'journal.journalLines.chartOfAccount',
        ])->findOrFail($id);

        // Security: non-master cannot view other branch adjustment
        if (! $isMaster && $adjustment->branch_id !== $user->branch_id) {
            abort(403, 'Anda tidak memiliki akses untuk melihat dokumen cabang lain.');
        }

        return view('inventory.adjustments.show', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'adjustment' => $adjustment,
        ]);
    }
}
