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
        Schema::create('laporan_sampah_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->string('tipe_laporan'); // 'kategori', 'sub_kategori', 'trend', 'nasabah'
            $table->string('periode'); // 'hari', 'minggu', 'bulan', 'tahun'
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->text('data_json'); // Menyimpan hasil laporan dalam format JSON
            $table->timestamp('last_generated_at');
            $table->timestamps();

            // Setiap tipe laporan harus unik per bank sampah dan periode
            $table->unique(['bank_sampah_id', 'tipe_laporan', 'periode', 'tanggal_mulai', 'tanggal_akhir']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_sampah_views');
    }
};