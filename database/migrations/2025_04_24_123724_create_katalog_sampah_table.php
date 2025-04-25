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
        Schema::create('katalog_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->tinyInteger('kategori_sampah'); // 0 = kering, 1 = basah
            $table->string('nama_item_sampah');
            $table->decimal('harga_per_kg', 10, 2);
            $table->text('deskripsi_item_sampah')->nullable();
            $table->text('cara_pemilahan')->nullable();
            $table->text('cara_pengemasahan')->nullable();
            $table->string('gambar_item_sampah')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('katalog_sampah');
    }
};