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
        Schema::create('member_bank_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bank_sampah_id')->constrained('bank_sampah')->onDelete('cascade');
            $table->string('kode_nasabah')->nullable();
            $table->date('tanggal_daftar');
            $table->string('status_keanggotaan')->default('aktif');
            $table->decimal('saldo', 10, 2)->default(0);
            $table->timestamps();

            // Ensure one user can only be member of one bank sampah once
            $table->unique(['user_id', 'bank_sampah_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_bank_sampah');
    }
};