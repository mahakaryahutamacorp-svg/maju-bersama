<?php

namespace App\Http\Controllers;

use App\Services\BranchStockCatalog;
use App\Services\OpeningBalanceService;
use App\Services\StockAdjustmentService;
use App\Support\DecimalQuantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Input stok awal hasil hitung fisik setelah stok direset ke nol.
 *
 * Setiap simpan menjadi dokumen penyesuaian stok sehingga muncul di Kartu Stok. Jurnalnya
 * Dr 1210 Persediaan / Cr 3110 Modal Awal, atau tanpa jurnal bila nilai persediaan sudah
 * dibukukan lewat menu Saldo Awal.
 */
class OpeningStockController extends Controller
{
    public function __construct(
        protected BranchStockCatalog $catalog,
        protected StockAdjustmentService $stockAdjustmentService
    ) {}

    public function create(Request $request): View
    {
        $user = $request->user()->load('branch');
        $currentBranch = $this->catalog->resolveBranch($user, $request->input('branch_id'));

        return view('inventory.opening-stock.create', [
            'isMaster' => $this->catalog->isMaster($user),
            'currentBranch' => $currentBranch,
            'branches' => $this->catalog->selectableBranches($user, $currentBranch),
            'products' => $this->catalog->productsFor($currentBranch),
            'inventoryAlreadyInOpeningBalance' => OpeningBalanceService::hasInventoryInOpeningBalance($currentBranch->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'record_journal' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => DecimalQuantity::rules(allowZero: true),
        ], [
            'items.required' => 'Isi stok minimal satu produk sebelum menyimpan.',
            'items.min' => 'Isi stok minimal satu produk sebelum menyimpan.',
            'items.*.quantity.min' => 'Stok awal tidak boleh kurang dari 0.',
            'items.*.product_id.distinct' => 'Produk yang sama terkirim lebih dari sekali.',
        ]);

        $user = $request->user();
        $branchId = $this->catalog->isMaster($user) ? ($validated['branch_id'] ?? $user->branch_id) : $user->branch_id;

        $lines = array_map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'actual_qty' => DecimalQuantity::normalize($item['quantity']),
            'reason' => StockAdjustmentService::OPENING_STOCK_NOTE,
        ], $validated['items']);

        $adjustment = $this->stockAdjustmentService->processAdjustment([
            'branch_id' => $branchId,
            'date' => $validated['date'],
            'notes' => trim(StockAdjustmentService::OPENING_STOCK_NOTE.' '.($validated['notes'] ?? '')),
            'is_opening_stock' => true,
            'record_journal' => (bool) $validated['record_journal'],
        ], $lines, $user);

        return redirect()
            ->route('inventory.adjustments.show', $adjustment->id)
            ->with('success', "Stok awal {$adjustment->lines->count()} produk tersimpan (dokumen #{$adjustment->reference_number}).");
    }
}
