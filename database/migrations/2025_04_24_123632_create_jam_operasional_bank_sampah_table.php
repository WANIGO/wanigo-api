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
        Schema::create('jam_operasional_bank_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->integer('day_of_week'); // 0 (Minggu) hingga 6 (Sabtu)
            $table->time('open_time');
            $table->time('close_time');
            $table->timestamps();

            // Kombinasi bank_sampah_id dan day_of_week harus unik
            $table->unique(['bank_sampah_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jam_operasional_bank_sampah');
    }
};