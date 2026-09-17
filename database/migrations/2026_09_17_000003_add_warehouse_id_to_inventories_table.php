<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safe additive migration — adds warehouse_id to inventories WITHOUT dropping branch_id.
     *
     * Design decisions:
     *  - warehouse_id is NULLABLE so existing tests that create Inventory rows without
     *    a warehouse (e.g. CheckoutTest, StockTransferTest) continue to pass unchanged.
     *    The NOT NULL constraint will be added in Phase 2 after all tests are updated.
     *  - For every existing production / seeded row, warehouse_id IS backfilled so data
     *    integrity is maintained from day one.
     *  - Using plain DB:: calls (no ->change()) to remain SQLite-compatible in CI/tests.
     *
     * Steps:
     *  1. Add warehouse_id as a nullable FK column.
     *  2. For every branch that has no warehouse yet, auto-create "Gudang Utama".
     *  3. Backfill warehouse_id for every existing inventory row via its branch_id.
     *  4. Add unique constraint (warehouse_id, product_id).
     */
    public function up(): void
    {
        // ── Step 1: Add nullable FK column ────────────────────────────────────
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('warehouses')
                ->cascadeOnDelete();
        });

        // ── Step 2: Seed "Gudang Utama" for every branch that has none ────────
        $branches = DB::table('branches')->whereNull('deleted_at')->get(['id', 'code', 'name']);

        foreach ($branches as $branch) {
            $baseCode = strtoupper(
                substr(preg_replace('/[^A-Z0-9]/i', '', $branch->code), 0, 12)
            ) . '-GU';

            $alreadyHasGudangUtama = DB::table('warehouses')
                ->where('branch_id', $branch->id)
                ->where('name', 'Gudang Utama')
                ->exists();

            if (! $alreadyHasGudangUtama) {
                $finalCode = $baseCode;
                $suffix    = 1;
                while (DB::table('warehouses')
                    ->where('branch_id', $branch->id)
                    ->where('code', $finalCode)
                    ->exists()
                ) {
                    $finalCode = $baseCode . $suffix++;
                }

                DB::table('warehouses')->insert([
                    'branch_id'  => $branch->id,
                    'name'       => 'Gudang Utama',
                    'code'       => $finalCode,
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── Step 3: Backfill warehouse_id from branch_id ──────────────────────
        $gudangUtamaMap = DB::table('warehouses')
            ->where('name', 'Gudang Utama')
            ->get(['id', 'branch_id'])
            ->keyBy('branch_id');

        $fallbackMap = DB::table('warehouses')
            ->orderBy('id')
            ->get(['id', 'branch_id'])
            ->groupBy('branch_id')
            ->map(fn ($rows) => $rows->first());

        DB::table('inventories')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($gudangUtamaMap, $fallbackMap) {
                foreach ($rows as $row) {
                    $warehouse = $gudangUtamaMap->get($row->branch_id)
                        ?? $fallbackMap->get($row->branch_id);

                    if ($warehouse) {
                        DB::table('inventories')
                            ->where('id', $row->id)
                            ->update(['warehouse_id' => $warehouse->id]);
                    }
                }
            });

        // ── Step 4: Add unique constraint (warehouse_id, product_id) ──────────
        // Rows with NULL warehouse_id (test-created rows) are excluded from the
        // unique check in SQLite & PostgreSQL; MySQL similarly ignores NULLs in
        // unique indexes — so existing test data cannot violate this constraint.
        Schema::table('inventories', function (Blueprint $table) {
            $table->unique(['warehouse_id', 'product_id'], 'inventories_warehouse_product_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique('inventories_warehouse_product_unique');
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
