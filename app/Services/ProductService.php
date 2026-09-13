<?php

namespace App\Services;

use App\Models\Product;
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

        return Product::create($data);
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
        $product->update($data);
        return $product->fresh();
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
