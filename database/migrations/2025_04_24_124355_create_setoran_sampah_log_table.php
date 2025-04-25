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
        Schema::create('setoran_sampah_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setoran_sampah_id')->constrained('setoran_sampah')->onDelete('cascade');
            $table->enum('status_setoran', ['Pengajuan', 'Diproses', 'Selesai', 'Dibatalkan']);
            $table->date('tanggal_status');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setoran_sampah_log');
    }
};