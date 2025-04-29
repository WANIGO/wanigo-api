<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriSampah;
use App\Models\SubKategoriSampah;
use App\Models\BankSampah;
use Illuminate\Support\Facades\DB;

class SubKategoriSampahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pastikan kategori utama sudah ada
        $kategoriKering = KategoriSampah::where('kode_kategori', 'kering')->first();
        $kategoriBasah = KategoriSampah::where('kode_kategori', 'basah')->first();

        if (!$kategoriKering || !$kategoriBasah) {
            $this->command->error('Kategori dasar tidak ditemukan. Jalankan KategoriSampahSeeder terlebih dahulu.');
            return;
        }

        // Dapatkan semua bank sampah
        $bankSampahList = BankSampah::all();

        foreach ($bankSampahList as $bankSampah) {
            // Cek apakah bank sampah ini sudah memiliki sub kategori
            $hasSubKategori = SubKategoriSampah::where('bank_sampah_id', $bankSampah->id)->exists();

            // Jika sudah ada sub kategori, skip
            if ($hasSubKategori) {
                $this->command->info("Bank sampah {$bankSampah->nama_bank_sampah} sudah memiliki sub kategori.");
                continue;
            }

            $this->command->info("Menambahkan sub kategori umum untuk bank sampah: {$bankSampah->nama_bank_sampah}");

            // Tambahkan sub kategori umum untuk sampah kering dan basah
            DB::table('sub_kategori_sampah')->insert([
                // Sub kategori umum untuk sampah kering
                [
                    'bank_sampah_id' => $bankSampah->id,
                    'kategori_sampah_id' => $kategoriKering->id,
                    'nama_sub_kategori' => 'Umum',
                    'kode_sub_kategori' => 'umum',
                    'deskripsi' => 'Semua jenis sampah kering',
                    'warna' => '#2196F3', // Blue
                    'urutan' => 1,
                    'status_aktif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],

                // Sub kategori umum untuk sampah basah
                [
                    'bank_sampah_id' => $bankSampah->id,
                    'kategori_sampah_id' => $kategoriBasah->id,
                    'nama_sub_kategori' => 'Umum',
                    'kode_sub_kategori' => 'umum',
                    'deskripsi' => 'Semua jenis sampah basah',
                    'warna' => '#4CAF50', // Green
                    'urutan' => 1,
                    'status_aktif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        $this->command->info('Sub kategori umum untuk sampah kering dan basah berhasil ditambahkan!');
    }
}