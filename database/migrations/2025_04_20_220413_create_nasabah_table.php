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
        Schema::create('nasabah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('jenis_kelamin')->nullable();
            $table->string('usia')->nullable();
            $table->string('profesi')->nullable();
            $table->string('tahu_memilah_sampah')->nullable();
            $table->string('motivasi_memilah_sampah')->nullable();
            $table->string('nasabah_bank_sampah')->nullable();
            $table->string('kode_bank_sampah')->nullable();
            $table->string('frekuensi_memilah_sampah')->nullable();
            $table->string('jenis_sampah_dikelola')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nasabah');
    }
};