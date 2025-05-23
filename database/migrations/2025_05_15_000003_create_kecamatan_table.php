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
        Schema::create('kecamatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kabupaten_kota_id')->constrained('kabupaten_kota')->onDelete('cascade');
            $table->string('nama_kecamatan');
            $table->string('kode_kecamatan');
            $table->timestamps();

            // Kode kecamatan harus unik per kabupaten/kota
            $table->unique(['kabupaten_kota_id', 'kode_kecamatan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kecamatan');
    }
};