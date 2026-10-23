<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branches')->update([
            'tax_enabled' => true,
            'tax_rate' => 11.00,
        ]);

        DB::table('products')->update([
            'is_taxable' => true,
        ]);
    }

    public function down(): void
    {
        DB::table('branches')->where('code', '!=', 'CBG-MAMA')->update([
            'tax_enabled' => false,
        ]);

        DB::table('products')->update([
            'is_taxable' => false,
        ]);
    }
};
