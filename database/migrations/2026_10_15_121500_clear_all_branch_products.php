<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog yang sudah diinput admin tidak dihapus.
    }

    public function down(): void
    {
        // Katalog yang dikosongkan tidak dikembalikan.
    }
};
