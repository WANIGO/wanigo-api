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
        Schema::create('sub_kategori_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->foreignId('kategori_sampah_id')->constrained('kategori_sampah')->onDelete('cascade');
            $table->string('nama_sub_kategori');
            $table->string('kode_sub_kategori');
            $table->text('deskripsi')->nullable();
            $table->string('icon')->nullable();
            $table->string('warna')->nullable(); // Untuk tampilan UI
            $table->integer('urutan')->default(0); // Untuk menentukan urutan tampilan
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            // Kode sub-kategori harus unik per bank sampah
            $table->unique(['bank_sampah_id', 'kode_sub_kategori']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_kategori_sampah');
    }
};