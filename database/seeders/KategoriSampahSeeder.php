<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KategoriSampah;
use Illuminate\Support\Facades\DB;

class KategoriSampahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cek jika data sudah ada
        if (KategoriSampah::count() > 0) {
            $this->command->info('Data kategori sampah sudah ada. Seeder dilewati.');
            return;
        }

        $this->command->info('Menambahkan data kategori sampah dasar...');

        // Tambahkan data dasar kategori sampah
        DB::table('kategori_sampah')->insert([
            [
                'nama_kategori' => 'Sampah Kering',
                'kode_kategori' => 'kering',
                'deskripsi' => 'Sampah kering adalah sampah yang tidak mengandung air dan dapat didaur ulang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_kategori' => 'Sampah Basah',
                'kode_kategori' => 'basah',
                'deskripsi' => 'Sampah basah adalah sampah organik yang dapat terurai secara alami',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('Data kategori sampah berhasil ditambahkan!');
    }
}