<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Pertanian' => Category::create(['name' => 'Pertanian']),
            'Elektronik' => Category::create(['name' => 'Elektronik']),
        ];

        $products = [
            [
                'branch_id' => 1,
                'category_id' => $categories['Pertanian']->id,
                'sku' => 'PRT-001',
                'name' => 'Pupuk Organik 25kg',
                'purchase_price' => 85000,
                'selling_price' => 110000,
                'stock' => 48,
            ],
            [
                'branch_id' => 1,
                'category_id' => $categories['Pertanian']->id,
                'sku' => 'PRT-002',
                'name' => 'Benih Jagung Hibrida',
                'purchase_price' => 42000,
                'selling_price' => 57500,
                'stock' => 120,
            ],
            [
                'branch_id' => 1,
                'category_id' => $categories['Elektronik']->id,
                'sku' => 'ELK-001',
                'name' => 'Timbangan Digital 30kg',
                'purchase_price' => 325000,
                'selling_price' => 425000,
                'stock' => 12,
            ],
            [
                'branch_id' => 1,
                'category_id' => $categories['Elektronik']->id,
                'sku' => 'ELK-002',
                'name' => 'Barcode Scanner USB',
                'purchase_price' => 275000,
                'selling_price' => 365000,
                'stock' => 7,
            ],
        ];

        foreach ($products as $row) {
            $product = Product::withoutGlobalScopes()->create($row);

            // Every seeded stock level needs a matching inventory row so the
            // per-branch stock table stays the single source of truth.
            Inventory::withoutGlobalScopes()->create([
                'branch_id' => $product->branch_id,
                'product_id' => $product->id,
                'quantity' => $product->stock,
            ]);
        }
    }
}