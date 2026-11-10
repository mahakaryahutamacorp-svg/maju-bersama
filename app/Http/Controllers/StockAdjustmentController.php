<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\StockAdjustment;
use App\Services\BranchStockCatalog;
use App\Services\StockAdjustmentService;
use App\Support\DecimalQuantity;
use Illuminate\Http\JsonResponse;
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

        $filtered = StockAdjustment::query()
            ->when(! $isMaster, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('date', '<=', $endDate))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            });

        $openingNote = StockAdjustmentService::OPENING_STOCK_NOTE.'%';
        $openingSummary = (clone $filtered)
            ->where('notes', 'like', $openingNote)
            ->selectRaw('COUNT(*) as documents, COALESCE(SUM(total_gain_value), 0) as gain, COALESCE(SUM(total_loss_value), 0) as loss')
            ->first();

        $opname = (clone $filtered)
            ->where(function ($query) use ($openingNote) {
                $query->whereNull('notes')
                    ->orWhere('notes', 'not like', $openingNote);
            });

        $opnameSummary = (clone $opname)
            ->selectRaw('COUNT(*) as documents, COALESCE(SUM(total_gain_value), 0) as gain, COALESCE(SUM(total_loss_value), 0) as loss')
            ->first();

        $adjustments = $opname
            ->with(['branch', 'items.product', 'journal'])
            ->latest('date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $totalAdjustments = (int) $opnameSummary->documents;
        $totalLoss = (float) $opnameSummary->loss;
        $totalGain = (float) $opnameSummary->gain;
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
            'openingStockDocuments' => (int) $openingSummary->documents,
            'openingStockGain' => (float) $openingSummary->gain,
            'openingStockLoss' => (float) $openingSummary->loss,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new stock adjustment.
     */
    public function create(Request $request, BranchStockCatalog $catalog): View
    {
        $user = $request->user()->load('branch');
        $currentBranch = $catalog->resolveBranch($user, $request->input('branch_id'));

        return view('inventory.adjustments.create', [
            'currentUser' => $user,
            'isMaster' => $catalog->isMaster($user),
            'currentBranch' => $currentBranch,
            'branches' => $catalog->selectableBranches($user, $currentBranch),
            'products' => $catalog->productsFor($currentBranch),
        ]);
    }

    /**
     * Store a newly created stock adjustment.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
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
            'items.*.actual_qty' => DecimalQuantity::rules(allowZero: true),
            'items.*.expected_qty' => DecimalQuantity::rules(required: false, allowZero: true),
            'items.*.system_qty' => DecimalQuantity::rules(required: false, allowZero: true),
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

        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Penyesuaian stok #{$adjustment->reference_number} berhasil diproses.",
                'data' => $adjustment->load(['items.product', 'branch', 'journal.journalLines.chartOfAccount']),
            ], 201);
        }

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
