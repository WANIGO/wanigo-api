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
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('konten_id')->constrained('kontens')->onDelete('cascade');
            $table->boolean('status')->default(false); // false=belum selesai, true=selesai
            $table->float('progress')->default(0); // Persentase progres (0-100)
            $table->timestamp('completed_at')->nullable(); // Waktu penyelesaian
            $table->timestamps();

            // Unique constraint untuk memastikan satu user hanya memiliki satu progress per konten
            $table->unique(['user_id', 'konten_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};