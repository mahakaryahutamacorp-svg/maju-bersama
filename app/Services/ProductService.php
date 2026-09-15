<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Create a new product with auto-generated SKU if not provided.
     *
     * @param array $data Validated product data
     * @param int $branchId Branch ID from authenticated user
     * @return Product
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
        if (!isset($data['stock'])) {
            $data['stock'] = 0;
        }

        return DB::transaction(function () use ($data, $branchId): Product {
            $product = Product::create($data);

            // Keep the per-branch stock table in sync with the catalogue row.
            Inventory::withoutGlobalScopes()->firstOrCreate(
                ['branch_id' => $branchId, 'product_id' => $product->id],
                ['quantity' => (int) $product->stock],
            );

            return $product;
        });
    }

    /**
     * Update an existing product.
     *
     * @param Product $product Product instance
     * @param array $data Validated update data
     * @return Product
     */
    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($data);

            if (array_key_exists('stock', $data)) {
                Inventory::withoutGlobalScopes()->updateOrCreate(
                    ['branch_id' => $product->branch_id, 'product_id' => $product->id],
                    ['quantity' => (int) $data['stock']],
                );
            }

            return $product->fresh();
        });
    }

    /**
     * Generate a unique SKU for a product.
     *
     * Format: BR{branch_id}-{random_6_chars}
     * Example: BR1-A3F9K2
     *
     * @param int $branchId
     * @return string
     */
    private function generateSku(int $branchId): string
    {
        do {
            $sku = 'BR' . $branchId . '-' . strtoupper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }
}
