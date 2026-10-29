<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'entity_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('entity_id')->nullable()->after('id')->constrained('entities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'entity_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entity_id');
        });
    }
};
