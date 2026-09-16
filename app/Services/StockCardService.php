<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockCardService
{
    /**
     * Get stock card movement history and running balance for a specific product and branch.
     *
     * @param int $branchId
     * @param int $productId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array{
     *     branch: Branch,
     *     product: Product,
     *     start_date: string|null,
     *     end_date: string|null,
     *     beginning_balance: int,
     *     movements: Collection,
     *     total_in: int,
     *     total_out: int,
     *     ending_balance: int,
     *     current_stock: int
     * }
     */
    public function getStockCard(int $branchId, int $productId, ?string $startDate = null, ?string $endDate = null): array
    {
        $branch = Branch::findOrFail($branchId);
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        // 1. Fetch all raw movements using union query
        $allMovements = $this->queryAllMovements($branchId, $productId);

        // 2. Calculate Beginning Balance (movements before start date)
        $beginningBalance = 0;
        if ($start) {
            $priorMovements = $allMovements->filter(function ($item) use ($start) {
                return Carbon::parse($item->transaction_date)->lt($start);
            });

            $priorIn = (int) $priorMovements->sum('qty_in');
            $priorOut = (int) $priorMovements->sum('qty_out');
            $beginningBalance = $priorIn - $priorOut;
        }

        // 3. Filter movements within the requested date range
        $periodMovements = $allMovements->filter(function ($item) use ($start, $end) {
            $tDate = Carbon::parse($item->transaction_date);
            if ($start && $tDate->lt($start)) {
                return false;
            }
            if ($end && $tDate->gt($end)) {
                return false;
            }
            return true;
        })->values();

        // 4. Calculate chronological running balance row by row
        $runningBalance = $beginningBalance;
        $totalIn = 0;
        $totalOut = 0;

        $movementsWithBalance = $periodMovements->map(function ($mov) use (&$runningBalance, &$totalIn, &$totalOut) {
            $qtyIn = (int) $mov->qty_in;
            $qtyOut = (int) $mov->qty_out;

            $totalIn += $qtyIn;
            $totalOut += $qtyOut;
            $runningBalance += ($qtyIn - $qtyOut);

            // Generate clean description in PHP layer
            $notes = $this->formatNotes($mov);

            return (object) [
                'date' => $mov->transaction_date,
                'created_at' => $mov->created_at,
                'reference_number' => $mov->reference_number,
                'type' => $mov->type,
                'notes' => $notes,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'running_balance' => $runningBalance,
            ];
        });

        $endingBalance = $runningBalance;

        // Fetch current on-hand stock from inventory table
        $inventory = Inventory::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->first();
        $currentStock = $inventory?->quantity ?? (int) $product->stock;

        return [
            'branch' => $branch,
            'product' => $product,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'beginning_balance' => $beginningBalance,
            'movements' => $movementsWithBalance,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'ending_balance' => $endingBalance,
            'current_stock' => $currentStock,
        ];
    }

    /**
     * Query all movements across transactions for branch and product.
     */
    private function queryAllMovements(int $branchId, int $productId): Collection
    {
        // 1. Penerimaan Barang (Goods Receipts) -> Masuk ke Pusat
        $goodsReceipts = DB::table('goods_receipts')
            ->join('goods_receipt_items', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.branch_id', $branchId)
            ->where('goods_receipt_items.product_id', $productId)
            ->select([
                'goods_receipts.date as transaction_date',
                'goods_receipts.created_at',
                'goods_receipts.reference_number',
                DB::raw("'goods_receipt' as movement_code"),
                DB::raw("'Penerimaan Barang (Goods Receipt)' as type"),
                'goods_receipts.supplier_name as raw_note',
                'goods_receipt_items.quantity as qty_in',
                DB::raw('0 as qty_out'),
            ]);

        // 2. Penjualan Kasir POS (Sales) -> Keluar
        $sales = DB::table('sales')
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sale_items.product_id', $productId)
            ->select([
                DB::raw('DATE(sales.created_at) as transaction_date'),
                'sales.created_at',
                'sales.receipt_number as reference_number',
                DB::raw("'sale' as movement_code"),
                DB::raw("'Penjualan POS' as type"),
                'sales.payment_method as raw_note',
                DB::raw('0 as qty_in'),
                'sale_items.quantity as qty_out',
            ]);

        // 3. Transfer Stok Keluar (Transfer Out) -> Keluar dari source_branch
        $transfersOut = DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->join('branches as dest_branch', 'stock_transfers.destination_branch_id', '=', 'dest_branch.id')
            ->where('stock_transfers.source_branch_id', $branchId)
            ->where('stock_transfer_items.source_product_id', $productId)
            ->where('stock_transfers.status', 'completed')
            ->select([
                'stock_transfers.transfer_date as transaction_date',
                'stock_transfers.created_at',
                'stock_transfers.reference_number',
                DB::raw("'transfer_out' as movement_code"),
                DB::raw("'Transfer Stok (Keluar)' as type"),
                'dest_branch.name as raw_note',
                DB::raw('0 as qty_in'),
                'stock_transfer_items.quantity as qty_out',
            ]);

        // 4. Transfer Stok Masuk (Transfer In) -> Masuk ke destination_branch
        $transfersIn = DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->join('branches as src_branch', 'stock_transfers.source_branch_id', '=', 'src_branch.id')
            ->where('stock_transfers.destination_branch_id', $branchId)
            ->where('stock_transfer_items.destination_product_id', $productId)
            ->where('stock_transfers.status', 'completed')
            ->select([
                'stock_transfers.transfer_date as transaction_date',
                'stock_transfers.created_at',
                'stock_transfers.reference_number',
                DB::raw("'transfer_in' as movement_code"),
                DB::raw("'Transfer Stok (Masuk)' as type"),
                'src_branch.name as raw_note',
                'stock_transfer_items.quantity as qty_in',
                DB::raw('0 as qty_out'),
            ]);

        // 5. Penyesuaian Stok / Opname (Stock Adjustment) -> Masuk jika plus, Keluar jika minus
        $adjustments = DB::table('stock_adjustments')
            ->join('stock_adjustment_items', 'stock_adjustments.id', '=', 'stock_adjustment_items.stock_adjustment_id')
            ->where('stock_adjustments.branch_id', $branchId)
            ->where('stock_adjustment_items.product_id', $productId)
            ->where('stock_adjustment_items.difference_qty', '!=', 0)
            ->select([
                'stock_adjustments.date as transaction_date',
                'stock_adjustments.created_at',
                'stock_adjustments.reference_number',
                DB::raw("'adjustment' as movement_code"),
                DB::raw("'Penyesuaian Stok (Opname)' as type"),
                'stock_adjustments.notes as raw_note',
                DB::raw('CASE WHEN stock_adjustment_items.difference_qty > 0 THEN stock_adjustment_items.difference_qty ELSE 0 END as qty_in'),
                DB::raw('CASE WHEN stock_adjustment_items.difference_qty < 0 THEN ABS(stock_adjustment_items.difference_qty) ELSE 0 END as qty_out'),
            ]);

        // Combine all using unionAll and order chronologically
        $query = $goodsReceipts
            ->unionAll($sales)
            ->unionAll($transfersOut)
            ->unionAll($transfersIn)
            ->unionAll($adjustments);

        return DB::query()->fromSub($query, 'movements')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Format descriptive notes based on movement code and raw data.
     */
    private function formatNotes(object $mov): string
    {
        return match ($mov->movement_code) {
            'goods_receipt' => $mov->raw_note ? "Supplier: {$mov->raw_note}" : 'Penerimaan Stok Pusat',
            'sale' => $mov->raw_note ? "Transaksi Kasir POS ({$mov->raw_note})" : 'Transaksi Kasir POS',
            'transfer_out' => "Kirim transfer ke cabang {$mov->raw_note}",
            'transfer_in' => "Terima transfer dari cabang {$mov->raw_note}",
            'adjustment' => $mov->raw_note ?: 'Rekonsiliasi Fisik Opname',
            default => '-',
        };
    }
}
