<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\Product;
use App\Services\StockCardService;
use Illuminate\Support\Collection;

class InventoryReportService
{
    public function __construct(
        protected StockCardService $stockCardService
    ) {}

    /**
     * Get stock card movement history, beginning balance, running balance, and product list.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $branchId
     * @param int|null $productId
     * @return array{
     *     branch: Branch|null,
     *     product: Product|null,
     *     start_date: string,
     *     end_date: string,
     *     beginning_balance: int,
     *     movements: Collection,
     *     total_in: int,
     *     total_out: int,
     *     ending_balance: int,
     *     current_stock: int,
     *     products: Collection
     * }
     */
    public function getStockCard(string $startDate, string $endDate, ?int $branchId = null, ?int $productId = null): array
    {
        $productsQuery = Product::withoutGlobalScopes();
        if ($branchId) {
            $productsQuery->where('branch_id', $branchId);
        }
        $products = $productsQuery->orderBy('name')->get();

        $effectiveBranchId = $branchId;
        if (! $effectiveBranchId) {
            $effectiveBranchId = Branch::query()->value('id') ?? 1;
        }

        $branch = Branch::query()->find($effectiveBranchId);

        if (! $productId && $products->isNotEmpty()) {
            $productId = $products->first()->id;
        }

        if (! $productId || $products->isEmpty()) {
            return [
                'branch' => $branch,
                'product' => null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'beginning_balance' => 0,
                'movements' => collect([]),
                'total_in' => 0,
                'total_out' => 0,
                'ending_balance' => 0,
                'current_stock' => 0,
                'products' => $products,
            ];
        }

        $stockData = $this->stockCardService->getStockCard(
            (int) $effectiveBranchId,
            (int) $productId,
            $startDate,
            $endDate
        );

        $stockData['products'] = $products;

        return $stockData;
    }
}
