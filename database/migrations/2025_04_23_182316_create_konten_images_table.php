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
        Schema::create('konten_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konten_id')->constrained('kontens')->onDelete('cascade');
            $table->string('image_url');
            $table->string('caption')->nullable();
            $table->integer('urutan')->default(0); // Urutan gambar dalam konten
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('konten_images');
    }
};