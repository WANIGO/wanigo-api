<?php

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
        Schema::create('kategori_sampah', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kategori'); // Sampah Kering atau Sampah Basah
            $table->string('kode_kategori')->unique(); // 'kering' atau 'basah'
            $table->text('deskripsi')->nullable();
            $table->string('icon')->nullable(); // Opsional, untuk menampilkan ikon kategori
            $table->timestamps();
        });

        // Isi data dasar kategori sampah
        DB::table('kategori_sampah')->insert([
            [
                'nama_kategori' => 'Sampah Kering',
                'kode_kategori' => 'kering',
                'deskripsi' => 'Sampah kering adalah sampah yang tidak mengandung air dan dapat didaur ulang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Sampah Basah',
                'kode_kategori' => 'basah',
                'deskripsi' => 'Sampah basah adalah sampah organik yang dapat terurai secara alami',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_sampah');
    }
};