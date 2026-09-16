<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Services\StockCardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCardController extends Controller
{
    public function __construct(
        protected StockCardService $stockCardService
    ) {}

    /**
     * Display the stock card report with movements and running balance.
     */
    public function index(Request $request): View
    {
        $user = $request->user()->load('branch');
        $isMaster = in_array($user->role, ['master', 'superadmin'], true);

        // Determine branch
        $branchId = (int) ($request->input('branch_id') ?: ($user->branch_id ?: Branch::whereNull('parent_id')->value('id') ?: Branch::value('id')));
        $productId = $request->input('product_id') ? (int) $request->input('product_id') : null;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $branches = $isMaster ? Branch::orderBy('name')->get() : collect([$user->branch]);

        // Get products available in the selected branch
        $products = Product::withoutGlobalScopes()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'stock', 'purchase_price']);

        if ($products->isEmpty() && $isMaster) {
            $products = Product::withoutGlobalScopes()->orderBy('name')->get(['id', 'sku', 'name', 'stock', 'purchase_price']);
        }

        // If no product selected but products exist, default to the first product if user requested report
        $stockCardData = null;
        if ($productId) {
            $stockCardData = $this->stockCardService->getStockCard($branchId, $productId, $startDate, $endDate);
        }

        return view('reports.inventory.stock-card', [
            'currentUser' => $user,
            'isMaster' => $isMaster,
            'branches' => $branches,
            'products' => $products,
            'selectedBranchId' => $branchId,
            'selectedProductId' => $productId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'stockCard' => $stockCardData,
        ]);
    }
}
