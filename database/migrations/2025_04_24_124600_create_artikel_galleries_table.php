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
        Schema::create('artikel_galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konten_artikel_id')->constrained('konten_artikels')->onDelete('cascade');
            $table->string('image_url');
            $table->string('caption')->nullable();
            $table->integer('urutan')->default(0); // Urutan gambar dalam galeri
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artikel_galleries');
    }
};