<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('purchase_order_id')->constrained('suppliers')->nullOnDelete();
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            $table->index(['supplier_id', 'payment_type']);
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreignId('goods_receipt_id')->nullable()->after('purchase_order_id')->constrained('goods_receipts')->nullOnDelete();
            $table->index('goods_receipt_id');
        });

        $this->backfillSupplierIds();
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropIndex(['goods_receipt_id']);
            $table->dropConstrainedForeignId('goods_receipt_id');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex(['supplier_id', 'payment_type']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('paid_amount');
        });
    }

    /**
     * Penerimaan dari PO mewarisi supplier PO. Penerimaan tanpa PO hanya ditautkan
     * bila nama pemasoknya cocok persis dengan tepat satu supplier di cabang yang sama.
     */
    private function backfillSupplierIds(): void
    {
        DB::table('goods_receipts')
            ->whereNotNull('purchase_order_id')
            ->orderBy('id')
            ->each(function ($receipt) {
                $supplierId = DB::table('purchase_orders')->where('id', $receipt->purchase_order_id)->value('supplier_id');
                if ($supplierId) {
                    DB::table('goods_receipts')->where('id', $receipt->id)->update(['supplier_id' => $supplierId]);
                }
            });

        DB::table('goods_receipts')
            ->whereNull('purchase_order_id')
            ->whereNotNull('supplier_name')
            ->orderBy('id')
            ->each(function ($receipt) {
                $matches = DB::table('suppliers')
                    ->where('branch_id', $receipt->branch_id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($receipt->supplier_name))])
                    ->pluck('id');

                if ($matches->count() === 1) {
                    DB::table('goods_receipts')->where('id', $receipt->id)->update(['supplier_id' => $matches->first()]);
                }
            });
    }
};
