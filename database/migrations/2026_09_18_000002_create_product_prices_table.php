<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('price_level_id')->nullable()->constrained('price_levels')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->unique(['product_id', 'price_level_id']);
        });

        // Backfill existing product selling prices to 'Harga Eceran'
        $defaultPriceLevel = DB::table('price_levels')
            ->where('name', 'Harga Eceran')
            ->first();

        if ($defaultPriceLevel) {
            DB::table('products')
                ->whereNotNull('selling_price')
                ->orderBy('id')
                ->chunk(200, function ($products) use ($defaultPriceLevel) {
                    $now = now();
                    $records = [];

                    foreach ($products as $product) {
                        $records[] = [
                            'product_id' => $product->id,
                            'price_level_id' => $defaultPriceLevel->id,
                            'price' => $product->selling_price,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($records)) {
                        DB::table('product_prices')->insert($records);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
