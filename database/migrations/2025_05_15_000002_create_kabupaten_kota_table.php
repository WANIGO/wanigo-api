<?php

namespace App\Models;

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
        Schema::create('kabupaten_kota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provinsi_id')->constrained('provinsi')->onDelete('cascade');
            $table->string('nama_kabupaten_kota');
            $table->string('kode_kabupaten_kota');
            $table->enum('tipe', ['kabupaten', 'kota']);
            $table->timestamps();

            // Kode kabupaten harus unik per provinsi
            $table->unique(['provinsi_id', 'kode_kabupaten_kota']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kabupaten_kota');
    }
};