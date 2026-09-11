<?php

namespace Database\Seeders;

use App\Models\Category;
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

        Product::insert([
            [
                'branch_id' => 1,
                'category_id' => $categories['Pertanian']->id,
                'sku' => 'PRT-001',
                'name' => 'Pupuk Organik 25kg',
                'purchase_price' => 85000,
                'selling_price' => 110000,
                'stock' => 48,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'category_id' => $categories['Pertanian']->id,
                'sku' => 'PRT-002',
                'name' => 'Benih Jagung Hibrida',
                'purchase_price' => 42000,
                'selling_price' => 57500,
                'stock' => 120,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'category_id' => $categories['Elektronik']->id,
                'sku' => 'ELK-001',
                'name' => 'Timbangan Digital 30kg',
                'purchase_price' => 325000,
                'selling_price' => 425000,
                'stock' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => 1,
                'category_id' => $categories['Elektronik']->id,
                'sku' => 'ELK-002',
                'name' => 'Barcode Scanner USB',
                'purchase_price' => 275000,
                'selling_price' => 365000,
                'stock' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}