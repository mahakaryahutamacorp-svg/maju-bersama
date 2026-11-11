<?php

use App\Models\CustomerGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baris product_prices yang memegang price_level_id sekaligus customer_group_id membuat
 * form produk menampilkan satu harga di dua kolom, sehingga mengubah "Harga Grosir"
 * tertimpa lagi oleh kolom grup. Grup dilepas hanya bila harga kasir tetap sama:
 * tingkat harga yang sepadan dengan grup, atau tingkat default yang sama dengan harga dasar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_prices', 'price_level_id') || ! Schema::hasColumn('product_prices', 'customer_group_id')) {
            return;
        }

        $defaultLevelId = DB::table('price_levels')->where('is_default', true)->value('id');

        DB::table('product_prices')
            ->join('products', 'products.id', '=', 'product_prices.product_id')
            ->whereNotNull('product_prices.price_level_id')
            ->whereNotNull('product_prices.customer_group_id')
            ->select([
                'product_prices.id',
                'product_prices.price_level_id',
                'product_prices.customer_group_id',
                'product_prices.price',
                'products.selling_price',
            ])
            ->chunkById(500, function ($rows) use ($defaultLevelId): void {
                $detachIds = [];

                foreach ($rows as $row) {
                    $matchingLevelId = CustomerGroup::matchingPriceLevelId((int) $row->customer_group_id);

                    $sameAsMatchingLevel = $matchingLevelId !== null
                        && (int) $matchingLevelId === (int) $row->price_level_id;

                    $sameAsBasePrice = $matchingLevelId === null
                        && $defaultLevelId !== null
                        && (int) $row->price_level_id === (int) $defaultLevelId
                        && bccomp((string) $row->price, (string) $row->selling_price, 2) === 0;

                    if ($sameAsMatchingLevel || $sameAsBasePrice) {
                        $detachIds[] = $row->id;
                    }
                }

                if ($detachIds !== []) {
                    DB::table('product_prices')->whereIn('id', $detachIds)->update(['customer_group_id' => null]);
                }
            }, 'product_prices.id', 'id');
    }

    public function down(): void
    {
        // Pelepasan grup tidak mengubah harga kasir, jadi tidak perlu dikembalikan.
    }
};
