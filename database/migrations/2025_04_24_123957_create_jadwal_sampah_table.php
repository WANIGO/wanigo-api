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
        Schema::create('jadwal_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->foreignId('tipe_jadwal_id')->constrained('tipe_jadwal_sampah')->onDelete('cascade');
            $table->string('frekuensi')->nullable(); // harian, mingguan, bulanan
            $table->time('waktu_mulai');
            $table->date('tanggal_mulai');
            $table->string('status')->default('belum selesai'); // belum selesai, selesai, berlangsung
            $table->integer('nomor_urut');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_sampah');
    }
};