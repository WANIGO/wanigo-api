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
        Schema::create('moduls', function (Blueprint $table) {
            $table->id();
            $table->string('judul_modul');
            $table->text('deskripsi');
            $table->text('objektif_modul');
            $table->text('benefit_modul');
            $table->string('status')->nullable()->default('belum selesai'); // belum selesai, selesai
            $table->integer('jumlah_konten')->default(0); // Untuk tracking jumlah konten dalam modul
            $table->integer('estimasi_waktu')->default(0); // Total estimasi waktu dalam detik
            $table->integer('poin')->default(0); // Total poin yang didapat jika menyelesaikan modul
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moduls');
    }
};