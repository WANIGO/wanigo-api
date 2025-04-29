<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pertama, tambah kolom sub_kategori_sampah_id baru
        Schema::table('katalog_sampah', function (Blueprint $table) {
            $table->foreignId('sub_kategori_sampah_id')->nullable()->after('bank_sampah_id');
        });

        // Migrasi data: ambil data kategori_sampah lama -> buat entri di sub_kategori_sampah -> update katalog_sampah
        // CATATAN: Bagian ini perlu dilakukan setelah populasi data sub_kategori_sampah
        // Atau alternatifnya gunakan artisan command khusus untuk migrasi data ini

        // Terakhir, hapus kolom kategori_sampah lama (setelah migrasi berhasil)
        // Kita tunda dulu penghapusan kolom ini untuk memastikan kompatibilitas mundur
        // dalam sebuah migration terpisah nanti

        // Schema::table('katalog_sampah', function (Blueprint $table) {
        //    $table->dropColumn('kategori_sampah');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('katalog_sampah', function (Blueprint $table) {
            // Cek apakah tabel dan kolom ada
            if (Schema::hasColumn('katalog_sampah', 'sub_kategori_sampah_id')) {
                // Cek foreign key constraint dengan metode yang lebih aman
                // Dapatkan semua foreign key constraints
                $foreignKeys = [];

                try {
                    // Dapatkan semua foreign keys pada tabel
                    $foreignKeys = Schema::getConnection()
                        ->getDoctrineSchemaManager()
                        ->listTableForeignKeys('katalog_sampah');

                    // Cari foreign key untuk kolom sub_kategori_sampah_id
                    $foreignKeyExists = false;
                    foreach ($foreignKeys as $foreignKey) {
                        if (in_array('sub_kategori_sampah_id', $foreignKey->getLocalColumns())) {
                            $foreignKeyExists = true;
                            break;
                        }
                    }

                    // Hanya hapus foreign key jika ada
                    if ($foreignKeyExists) {
                        $table->dropForeign(['sub_kategori_sampah_id']);
                    }
                } catch (\Exception $e) {
                    // Jika terjadi error (foreign key tidak ada), log dan lanjutkan proses
                    Log::info('Error checking foreign key: ' . $e->getMessage());
                }

                // Hapus kolom
                $table->dropColumn('sub_kategori_sampah_id');
            }
        });
    }
};