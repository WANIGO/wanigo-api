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
        Schema::create('kontens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modul_id')->constrained('moduls')->onDelete('cascade');
            $table->enum('tipe_konten', ['artikel', 'video', 'infografis']);
            $table->string('judul_konten');
            $table->text('deskripsi');
            $table->integer('urutan'); // Menentukan urutan konten dalam modul
            $table->integer('durasi')->default(0); // Durasi dalam detik
            $table->integer('poin')->default(0); // Poin yang didapat jika menyelesaikan konten
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontens');
    }
};