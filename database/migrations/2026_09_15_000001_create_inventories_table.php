<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventory is stored per branch so that the same product can be held by the
     * central entity and its branches at the same time. The composite unique key on
     * (branch_id, product_id) guarantees a single stock row per product per branch,
     * which is what makes stock transfers safe from duplicated rows.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id'], 'inventories_branch_product_unique');
            $table->index('product_id');
        });

        // Backfill existing per-product stock into the new per-branch inventory table.
        DB::table('products')->orderBy('id')->chunk(200, function ($products): void {
            $rows = [];

            foreach ($products as $product) {
                $rows[] = [
                    'branch_id' => $product->branch_id,
                    'product_id' => $product->id,
                    'quantity' => $product->stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                DB::table('inventories')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
