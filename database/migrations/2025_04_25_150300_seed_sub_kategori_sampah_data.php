<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SubKategoriSampah;
use App\Models\KategoriSampah;
use App\Models\BankSampah;

class SeedSubKategoriSampahData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Seed data kategori sampah
        $kategoriKering = KategoriSampah::where('kode_kategori', 'kering')->first();
        $kategoriBasah = KategoriSampah::where('kode_kategori', 'basah')->first();

        // Get the first bank sampah for example
        $bankSampah = BankSampah::first();

        if ($bankSampah && $kategoriKering) {
            // Seed sub kategori sampah kering
            SubKategoriSampah::create([
                'bank_sampah_id' => $bankSampah->id,
                'kategori_sampah_id' => $kategoriKering->id,
                'nama_sub_kategori' => 'Kertas',
                'kode_sub_kategori' => 'kertas',
                'deskripsi' => 'Semua jenis kertas dan kardus',
                'icon' => 'paper.png',
                'warna' => '#2196F3',
                'urutan' => 1,
                'status_aktif' => true,
            ]);

            // Add more sub-categories as needed
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Optionally remove the seeded data
        SubKategoriSampah::whereIn('kode_sub_kategori', ['kertas'])->delete();
    }
}