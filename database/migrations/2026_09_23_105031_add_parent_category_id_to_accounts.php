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
        $accountTables = array_filter(['accounts', 'chart_of_accounts'], fn ($t) => Schema::hasTable($t));
        foreach ($accountTables as $table) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                $tableBlueprint->foreignId('parent_category_id')->nullable()->constrained($table)->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $accountTables = array_filter(['accounts', 'chart_of_accounts'], fn ($t) => Schema::hasTable($t));
        foreach ($accountTables as $table) {
            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->dropConstrainedForeignId('parent_category_id');
            });
        }
    }
};
