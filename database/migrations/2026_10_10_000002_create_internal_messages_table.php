<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // NULL = Pusat. Disalin saat kirim agar riwayat tetap utuh walau user pindah cabang.
            $table->foreignId('sender_branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            // NULL = Pusat.
            $table->foreignId('receiver_branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['sender_branch_id', 'receiver_branch_id', 'id']);
            $table->index(['receiver_branch_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_messages');
    }
};
