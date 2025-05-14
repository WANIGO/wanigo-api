<?php

namespace App\Models;

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
        Schema::table('bank_sampah', function (Blueprint $table) {
            // Tambahkan foreign key untuk tipe bank sampah
            $table->foreignId('tipe_bank_sampah_id')->nullable()->after('id');

            // Tambahkan foreign key untuk lokasi
            $table->foreignId('provinsi_id')->nullable()->after('tonase_sampah');
            $table->foreignId('kabupaten_kota_id')->nullable()->after('provinsi_id');
            $table->foreignId('kecamatan_id')->nullable()->after('kabupaten_kota_id');
            $table->foreignId('kelurahan_desa_id')->nullable()->after('kecamatan_id');

            // Tambahkan kolom kode_admin jika diperlukan
            $table->string('kode_admin')->nullable()->after('nama_bank_sampah');

            // Tambahkan foreign key constraints
            $table->foreign('tipe_bank_sampah_id')->references('id')->on('tipe_bank_sampah');
            $table->foreign('provinsi_id')->references('id')->on('provinsi');
            $table->foreign('kabupaten_kota_id')->references('id')->on('kabupaten_kota');
            $table->foreign('kecamatan_id')->references('id')->on('kecamatan');
            $table->foreign('kelurahan_desa_id')->references('id')->on('kelurahan_desa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_sampah', function (Blueprint $table) {
            // Hapus foreign key constraints terlebih dahulu
            $table->dropForeign(['tipe_bank_sampah_id']);
            $table->dropForeign(['provinsi_id']);
            $table->dropForeign(['kabupaten_kota_id']);
            $table->dropForeign(['kecamatan_id']);
            $table->dropForeign(['kelurahan_desa_id']);

            // Hapus kolom
            $table->dropColumn('tipe_bank_sampah_id');
            $table->dropColumn('kode_admin');
            $table->dropColumn('provinsi_id');
            $table->dropColumn('kabupaten_kota_id');
            $table->dropColumn('kecamatan_id');
            $table->dropColumn('kelurahan_desa_id');
        });
    }
};