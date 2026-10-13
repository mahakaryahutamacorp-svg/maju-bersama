<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('catalog', 20)->nullable()->after('name')->index();
        });

        DB::table('categories')
            ->whereIn('name', ['Pestisida', 'Pupuk', 'ZPT'])
            ->update(['catalog' => 'pupuk']);

        $genericPakan = DB::table('categories')->where('name', 'Pakan')->first();
        if ($genericPakan && ! DB::table('products')->where('category_id', $genericPakan->id)->exists()) {
            DB::table('categories')->where('id', $genericPakan->id)->delete();
        }

        $now = now();
        foreach ([
            'Pakan Ayam',
            'Pakan Bebek',
            'Pakan Sapi',
            'Pakan Kambing',
            'Pakan Ikan',
            'Konsentrat',
            'Dedak & Bekatul',
            'Jagung',
            'Vitamin & Obat Ternak',
            'Peralatan Ternak',
        ] as $name) {
            $exists = DB::table('categories')->where('name', $name)->exists();
            if ($exists) {
                DB::table('categories')->where('name', $name)->update(['catalog' => 'pakan']);

                continue;
            }

            DB::table('categories')->insert([
                'name' => $name,
                'catalog' => 'pakan',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['catalog']);
            $table->dropColumn('catalog');
        });
    }
};
