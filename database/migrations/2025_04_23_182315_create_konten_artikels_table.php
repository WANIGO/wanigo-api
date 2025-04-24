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
        Schema::create('konten_artikels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konten_id')->constrained('kontens')->onDelete('cascade');
            $table->text('content'); // Konten artikel dalam format HTML atau plain text
            $table->string('thumbnail_url')->nullable(); // URL gambar thumbnail artikel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('konten_artikels');
    }
};