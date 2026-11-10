<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Support\DecimalQuantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        protected ?StockAdjustmentService $stockAdjustmentService = null
    ) {
        $this->stockAdjustmentService = $stockAdjustmentService ?? app(StockAdjustmentService::class);
    }

    /**
     * Create a new product with auto-generated SKU if not provided.
     *
     * @param  array  $data  Validated product data
     * @param  int  $branchId  Branch ID from authenticated user
     */
    public function createProduct(array $data, int $branchId): Product
    {
        // Auto-generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku($branchId);
        }

        // Set branch_id from authenticated user (security: prevent manual override)
        $data['branch_id'] = $branchId;

        // Set default stock to 0 if not provided
        if (! isset($data['stock'])) {
            $data['stock'] = 0;
        }

        if (! array_key_exists('is_taxable', $data)) {
            $data['is_taxable'] = true;
        }

        $openingQuantity = DecimalQuantity::normalize($data['stock'] ?? 0);
        $data['stock'] = 0;

        return DB::transaction(function () use ($data, $branchId, $openingQuantity): Product {
            $product = Product::create($data);

            Inventory::withoutGlobalScopes()->firstOrCreate(
                ['branch_id' => $branchId, 'product_id' => $product->id],
                ['quantity' => 0],
            );

            if (DecimalQuantity::compare($openingQuantity, '0') > 0) {
                $this->recordStockDocument($product, $openingQuantity, true);
            }

            return $product->fresh();
        });
    }

    /**
     * Update an existing product.
     *
     * @param  Product  $product  Product instance
     * @param  array  $data  Validated update data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $targetQuantity = array_key_exists('stock', $data) ? DecimalQuantity::normalize($data['stock'] ?? 0) : null;
        unset($data['stock']);

        return DB::transaction(function () use ($product, $data, $targetQuantity): Product {
            $product->update($data);

            if ($targetQuantity !== null && DecimalQuantity::compare($targetQuantity, $product->availableQuantity()) !== 0) {
                $this->recordStockDocument($product, $targetQuantity, false);
            }

            return $product->fresh();
        });
    }

    /**
     * Stok hanya berubah lewat dokumen penyesuaian, supaya kartu stok dan jurnal persediaan ikut tercatat.
     * Stok awal dibukukan ke modal kecuali persediaan cabang sudah masuk saldo awal.
     */
    private function recordStockDocument(Product $product, string $actualQuantity, bool $isOpeningStock): void
    {
        $notes = $isOpeningStock ? StockAdjustmentService::OPENING_STOCK_NOTE : 'Koreksi stok dari data produk';

        $this->stockAdjustmentService->processAdjustment([
            'branch_id' => $product->branch_id,
            'date' => now()->toDateString(),
            'notes' => $notes,
            'is_opening_stock' => $isOpeningStock,
            'record_journal' => ! $isOpeningStock || ! OpeningBalanceService::hasInventoryInOpeningBalance((int) $product->branch_id),
        ], [['product_id' => $product->id, 'actual_qty' => $actualQuantity, 'reason' => $notes]]);
    }

    /**
     * Delete a product and its associated inventory.
     */
    public function deleteProduct(Product $product, bool $allowWithHistory = false): bool
    {
        if (! $allowWithHistory && $product->hasTransactionHistory()) {
            throw ValidationException::withMessages([
                'product' => [Product::HISTORY_DELETE_MESSAGE],
            ]);
        }

        return DB::transaction(function () use ($product): bool {
            Inventory::withoutGlobalScopes()
                ->where('product_id', $product->id)
                ->delete();

            return (bool) $product->delete();
        });
    }

    /**
     * Generate a unique SKU for a product.
     *
     * Format: BR{branch_id}-{random_6_chars}
     * Example: BR1-A3F9K2
     */
    private function generateSku(int $branchId): string
    {
        do {
            $sku = 'BR'.$branchId.'-'.strtoupper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }
}
