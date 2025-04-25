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
        Schema::create('setoran_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->date('tanggal_setoran');
            $table->decimal('total_saldo', 10, 2)->default(0);
            $table->decimal('total_berat', 10, 2)->default(0);
            $table->enum('status_setoran', ['Pengajuan', 'Diproses', 'Selesai', 'Dibatalkan'])->default('Pengajuan');
            $table->string('kode_setoran_sampah')->unique();
            $table->integer('total_poin')->default(0);
            $table->text('catatan_status_setoran')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setoran_sampah');
    }
};