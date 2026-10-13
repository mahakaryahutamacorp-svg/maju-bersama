<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasTable('products') || ! Schema::hasTable('branches')) {
            return;
        }

        $keptSales = DB::table('sales')
            ->where(function ($query) {
                $query->whereIn('payment_status', ['UNPAID', 'PARTIAL'])
                    ->orWhereRaw('ROUND(COALESCE(total_amount,0) - COALESCE(discount_amount,0) - COALESCE(paid_amount,0), 2) > 0');
            })
            ->get(['id', 'receipt_number']);

        $keptSaleIds = $keptSales->pluck('id')->map(fn ($id) => (int) $id)->all();
        $receipts = $keptSales->pluck('receipt_number')->filter()->map(fn ($receipt) => (string) $receipt)->all();
        $receiptLookup = array_fill_keys($receipts, true);

        $keepJournalIds = [];
        foreach (['fixed_assets', 'asset_depreciations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'journal_header_id')) {
                foreach (DB::table($table)->whereNotNull('journal_header_id')->pluck('journal_header_id') as $id) {
                    $keepJournalIds[(int) $id] = true;
                }
            }
        }

        if (Schema::hasTable('journal_headers')) {
            foreach (DB::table('journal_headers')->get(['id', 'reference_number', 'description']) as $header) {
                $reference = (string) $header->reference_number;
                $stripped = (string) preg_replace('/^JV-/', '', $reference);
                $description = (string) $header->description;
                $matched = isset($receiptLookup[$reference]) || ($stripped !== '' && isset($receiptLookup[$stripped]));

                if (! $matched) {
                    foreach ($receipts as $receipt) {
                        if ($receipt !== '' && str_contains($description, $receipt)) {
                            $matched = true;
                            break;
                        }
                    }
                }

                if ($matched) {
                    $keepJournalIds[(int) $header->id] = true;
                }
            }
        }

        $this->deleteWhere('sale_items', function ($query) use ($keptSaleIds) {
            if ($keptSaleIds !== []) {
                $query->whereNotIn('sale_id', $keptSaleIds);
            }
        });
        $this->deleteAll('sales_return_items');
        $this->deleteAll('sales_returns');

        if (Schema::hasTable('payment_allocations')) {
            DB::table('payment_allocations')
                ->when($keptSaleIds !== [], fn ($query) => $query->where(function ($inner) use ($keptSaleIds) {
                    $inner->whereNull('sale_id')->orWhereNotIn('sale_id', $keptSaleIds);
                }))
                ->delete();

            $remainingPaymentIds = DB::table('payment_allocations')->pluck('payment_id')->all();
            if (Schema::hasTable('payments')) {
                $paymentQuery = DB::table('payments');
                if ($remainingPaymentIds !== []) {
                    $paymentQuery->whereNotIn('id', $remainingPaymentIds);
                }
                $paymentQuery->delete();

                foreach (DB::table('payments')->whereNotNull('journal_header_id')->pluck('journal_header_id') as $id) {
                    $keepJournalIds[(int) $id] = true;
                }
            }
        }

        $this->deleteAll('goods_receipt_items');
        $this->deleteAll('goods_receipts');
        $this->deleteAll('purchase_return_items');
        $this->deleteAll('purchase_returns');
        $this->deleteAll('purchase_order_items');
        $this->deleteAll('purchase_payments');
        $this->deleteAll('purchases');
        $this->deleteAll('supplier_payments');
        $this->deleteAll('stock_transfer_items');
        $this->deleteAll('stock_transfers');
        $this->deleteAll('stock_adjustment_lines');
        $this->deleteAll('stock_adjustment_items');
        $this->deleteAll('stock_adjustments');
        $this->deleteAll('expenses');
        $this->deleteAll('cash_transfers');

        if (Schema::hasColumn('sales', 'cash_register_shift_id')) {
            DB::table('sales')->whereNotNull('cash_register_shift_id')->update(['cash_register_shift_id' => null]);
        }
        $this->deleteAll('cash_register_shifts');
        $this->deleteAll('purchase_orders');

        if ($keptSaleIds === []) {
            $this->deleteAll('sales');
        } else {
            DB::table('sales')->whereNotIn('id', $keptSaleIds)->delete();
        }

        if (Schema::hasTable('journal_lines') && Schema::hasTable('journal_headers')) {
            $dropJournalIds = DB::table('journal_headers')
                ->when($keepJournalIds !== [], fn ($query) => $query->whereNotIn('id', array_keys($keepJournalIds)))
                ->pluck('id')
                ->all();

            if ($dropJournalIds !== []) {
                DB::table('journal_lines')->whereIn('journal_header_id', $dropJournalIds)->delete();
                DB::table('journal_headers')->whereIn('id', $dropJournalIds)->delete();
            }
        }

        DB::table('products')->update(['stock' => 0, 'updated_at' => now()]);
        if (Schema::hasTable('inventories')) {
            DB::table('inventories')->update(['quantity' => 0, 'updated_at' => now()]);
        }

        $this->shareCatalog();
    }

    public function down(): void
    {
        // Data transaksi uji coba yang dihapus tidak dikembalikan.
    }

    private function deleteAll(string $table): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->delete();
        }
    }

    private function deleteWhere(string $table, callable $constraint): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table);
        $constraint($query);
        $query->delete();
    }

    private function shareCatalog(): void
    {
        $source = DB::table('branches')->where('code', 'GUDANG-PUPUK')->first();
        $sourceCount = $source
            ? DB::table('products')->where('branch_id', $source->id)->whereNull('deleted_at')->count()
            : 0;

        if ($sourceCount === 0) {
            $best = DB::table('products')
                ->whereNull('deleted_at')
                ->selectRaw('branch_id, COUNT(*) as total')
                ->groupBy('branch_id')
                ->orderByDesc('total')
                ->first();
            $source = $best ? DB::table('branches')->where('id', $best->branch_id)->first() : null;
        }

        if (! $source) {
            return;
        }

        $products = DB::table('products')
            ->where('branch_id', $source->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $columns = Schema::getColumnListing('products');
        $targets = DB::table('branches')
            ->where('id', '!=', $source->id)
            ->where('code', 'not like', 'OFFICE-%')
            ->where('code', '!=', 'GUDANG-PAKAN')
            ->orderBy('id')
            ->get();

        foreach ($targets as $target) {
            $warehouse = $this->ensureWarehouse($target);

            foreach ($products as $product) {
                $existing = DB::table('products')
                    ->where('branch_id', $target->id)
                    ->where('sku', $product->sku)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    DB::table('products')->where('id', $existing->id)->update([
                        'name' => $product->name,
                        'unit' => $product->unit ?? null,
                        'category_id' => $product->category_id,
                        'purchase_price' => $product->purchase_price,
                        'selling_price' => $product->selling_price,
                        'stock' => 0,
                        'updated_at' => now(),
                    ]);
                    $productId = (int) $existing->id;
                } else {
                    $insert = [];
                    foreach ($columns as $column) {
                        if ($column === 'id') {
                            continue;
                        }
                        $insert[$column] = $product->{$column} ?? null;
                    }
                    $insert['branch_id'] = $target->id;
                    $insert['stock'] = 0;
                    $insert['created_at'] = now();
                    $insert['updated_at'] = now();
                    if (array_key_exists('deleted_at', $insert)) {
                        $insert['deleted_at'] = null;
                    }
                    $productId = (int) DB::table('products')->insertGetId($insert);

                    if (Schema::hasTable('product_prices')) {
                        foreach (DB::table('product_prices')->where('product_id', $product->id)->get() as $price) {
                            $priceRow = (array) $price;
                            unset($priceRow['id']);
                            $priceRow['product_id'] = $productId;
                            $priceRow['created_at'] = now();
                            $priceRow['updated_at'] = now();
                            DB::table('product_prices')->insert($priceRow);
                        }
                    }
                }

                if ($warehouse && Schema::hasTable('inventories')) {
                    $inventory = DB::table('inventories')
                        ->where('branch_id', $target->id)
                        ->where('product_id', $productId)
                        ->first();

                    if ($inventory) {
                        DB::table('inventories')->where('id', $inventory->id)->update([
                            'warehouse_id' => $warehouse->id,
                            'quantity' => 0,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('inventories')->insert([
                            'branch_id' => $target->id,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $productId,
                            'quantity' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    private function ensureWarehouse(object $branch): ?object
    {
        if (! Schema::hasTable('warehouses')) {
            return null;
        }

        $warehouse = DB::table('warehouses')
            ->where('branch_id', $branch->id)
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        if ($warehouse) {
            return $warehouse;
        }

        $code = strtoupper(substr((string) preg_replace('/[^A-Z0-9]/i', '', (string) $branch->code), 0, 12)).'-GU';
        $final = $code;
        $suffix = 1;
        while (DB::table('warehouses')->where('branch_id', $branch->id)->where('code', $final)->exists()) {
            $final = substr($code, 0, 17).$suffix++;
        }

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branch->id,
            'code' => $final,
            'name' => 'Gudang Utama',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('warehouses')->where('id', $warehouseId)->first();
    }
};
