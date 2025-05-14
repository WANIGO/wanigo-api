<?php

namespace App\Models;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tambahkan kolom yang belum ada pada point_transactions
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('points');
            $table->string('transaction_type'); // misalnya: 'earn', 'redeem', 'bonus', etc.
            $table->text('description')->nullable();
            $table->nullableMorphs('reference'); // Untuk referensi ke konten, modul, atau transaksi lainnya
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            // Hapus foreign key constraints terlebih dahulu
            $table->dropForeign(['user_id']);

            // Hapus kolom
            $table->dropColumn(['user_id', 'points', 'transaction_type', 'description']);
            $table->dropMorphs('reference');
        });
    }
};