<?php

use App\Support\Database\RollbackGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'pos_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->restrictOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->string('payment_type', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('sale_items', 'unit_cost')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->decimal('unit_cost', 18, 4)->nullable();
                $table->decimal('cogs_amount', 18, 2)->nullable();
                $table->string('cost_source', 20)->nullable();
            });
        }

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->default(0)->change();
        });

        if (! Schema::hasColumn('sales_return_items', 'sale_item_id')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
                $table->string('cost_source', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('customers', 'credit_limit')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('code', 30)->nullable()->unique();
                $table->decimal('credit_limit', 18, 2)->nullable();
                $table->unsignedSmallInteger('payment_terms_days')->nullable();
            });
        }

        $this->addPaymentCustomerForeignKeyWhenClean();
    }

    public function down(): void
    {
        RollbackGuard::assertColumnsEmpty('sales', ['pos_session_id', 'journal_entry_id', 'payment_type']);
        RollbackGuard::assertColumnsEmpty('sale_items', ['unit_cost', 'cogs_amount', 'cost_source']);
        RollbackGuard::assertColumnsEmpty('sales_return_items', ['sale_item_id', 'cost_source']);
        RollbackGuard::assertColumnsEmpty('customers', ['code', 'credit_limit', 'payment_terms_days']);
        $this->assertReturnUnitCostFitsTwoDecimals();

        if ($this->hasForeignKey('payments', 'customer_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
            });
        }

        if (Schema::hasIndex('payments', 'payments_customer_id_foreign')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('payments_customer_id_foreign');
            });
        }

        if (Schema::hasColumn('customers', 'credit_limit')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn(['code', 'credit_limit', 'payment_terms_days']);
            });
        }

        if (Schema::hasColumn('sales_return_items', 'sale_item_id')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sale_item_id');
                $table->dropColumn('cost_source');
            });
        }

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->change();
        });

        if (Schema::hasColumn('sale_items', 'unit_cost')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropColumn(['unit_cost', 'cogs_amount', 'cost_source']);
            });
        }

        if (Schema::hasColumn('sales', 'pos_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pos_session_id');
                $table->dropConstrainedForeignId('journal_entry_id');
                $table->dropColumn('payment_type');
            });
        }
    }

    /**
     * FK hanya ditambahkan bila tidak ada payments.customer_id yatim; bila ada, dilewati dan dicatat.
     * RESTRICT sejalan dengan aturan aplikasi yang menolak hapus pelanggan yang punya pembayaran.
     */
    private function addPaymentCustomerForeignKeyWhenClean(): void
    {
        if ($this->hasForeignKey('payments', 'customer_id')) {
            return;
        }

        $orphanCount = DB::table('payments')
            ->leftJoin('customers', 'customers.id', '=', 'payments.customer_id')
            ->whereNotNull('payments.customer_id')
            ->whereNull('customers.id')
            ->count();

        if ($orphanCount > 0) {
            Log::warning("Ledger v2: FK payments.customer_id dilewati, ada {$orphanCount} pembayaran dengan customer_id yatim.");

            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });
    }

    /**
     * Mengembalikan unit_cost ke decimal(15,2) hanya aman bila tidak ada nilai yang terpotong.
     */
    private function assertReturnUnitCostFitsTwoDecimals(): void
    {
        $lossy = DB::table('sales_return_items')
            ->where(function ($query) {
                $query->whereRaw('unit_cost <> ROUND(unit_cost, 2)')
                    ->orWhere('unit_cost', '>=', 10000000000000)
                    ->orWhere('unit_cost', '<=', -10000000000000);
            })
            ->exists();

        if ($lossy) {
            throw new RuntimeException('Rollback dibatalkan: sales_return_items.unit_cost berisi nilai yang akan terpotong bila dikembalikan ke decimal(15,2).');
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => $foreignKey['columns'] === [$column]);
    }
};
