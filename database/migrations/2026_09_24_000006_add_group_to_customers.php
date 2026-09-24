<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
                $table->timestamps();

                $table->index(['branch_id', 'customer_group_id']);
                $table->index('name');
            });
        } else {
            Schema::table('customers', function (Blueprint $table) {
                if (! Schema::hasColumn('customers', 'customer_group_id')) {
                    $table->foreignId('customer_group_id')->nullable()->after('id')->constrained('customer_groups')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (Schema::hasColumn('customers', 'customer_group_id')) {
                    $table->dropForeign(['customer_group_id']);
                    $table->dropColumn('customer_group_id');
                }
            });
        }
    }
};
