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
        Schema::create('bank_sampah', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bank_sampah');
            $table->string('alamat_bank_sampah');
            $table->text('deskripsi')->nullable(); // Deskripsi singkat tentang bank sampah
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->boolean('status_operasional')->default(true);
            $table->date('tanggal_setoran')->nullable();
            $table->integer('jumlah_nasabah')->default(0);
            $table->string('email')->nullable(); // Email kontak bank sampah
            $table->string('nomor_telepon_publik')->nullable(); // Diubah menjadi telepon sesuai kebutuhan
            $table->string('foto_usaha')->nullable(); // URL atau path ke foto profil bank sampah
            $table->decimal('tonase_sampah', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_sampah');
    }
};