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
        Schema::create('konten_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konten_id')->constrained('kontens')->onDelete('cascade');
            $table->string('video_url'); // URL video (YouTube atau lainnya)
            $table->integer('durasi'); // Durasi video dalam detik
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('konten_videos');
    }
};