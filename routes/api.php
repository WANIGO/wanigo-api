<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Nasabah\ProfilNasabahController;
use App\Http\Controllers\API\Nasabah\EdukasiController;
use App\Http\Controllers\API\Nasabah\MemberBankSampahController;
use App\Http\Controllers\API\Nasabah\KatalogSampahController;
use App\Http\Controllers\API\Nasabah\SetoranSampahController;
use App\Http\Controllers\API\Nasabah\DetailSetoranController;
use App\Http\Controllers\API\Nasabah\SubKategoriSampahController;
use App\Http\Controllers\API\Nasabah\JadwalSampahController;
use App\Http\Controllers\API\Nasabah\ProfilBankSampahController;
use App\Http\Controllers\API\Nasabah\LaporanSampahController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/
// Rute Autentikasi (publik)
Route::post('/check-email', [AuthController::class, 'checkEmail']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rute yang memerlukan otentikasi
Route::middleware('auth:sanctum')->group(function () {
    // Rute untuk semua pengguna
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::get('/profile-status', [AuthController::class, 'checkProfileStatus']);

    // Rute khusus nasabah
    Route::prefix('nasabah')->group(function () {
        // Profil Nasabah
        Route::get('/profile', [ProfilNasabahController::class, 'getProfile']);
        Route::post('/profile/step1', [ProfilNasabahController::class, 'updateProfileStep1']);
        Route::post('/profile/step2', [ProfilNasabahController::class, 'updateProfileStep2']);
        Route::post('/profile/step3', [ProfilNasabahController::class, 'updateProfileStep3']);

        // Profil Bank Sampah - Format asli
        Route::prefix('bank-sampah-profil')->group(function () {
            Route::get('/{id}', [ProfilBankSampahController::class, 'getBankSampah']);
            Route::get('/{id}/katalog', [ProfilBankSampahController::class, 'getKatalogSampah']);
            Route::get('/{id}/sub-kategori', [ProfilBankSampahController::class, 'getSubKategoriSampah']);
            Route::get('/{id}/jam-operasional', [ProfilBankSampahController::class, 'getJamOperasional']);
            Route::get('/{id}/lokasi', [ProfilBankSampahController::class, 'getLokasiBank']);
            Route::get('/{id}/kontak', [ProfilBankSampahController::class, 'getKontakBank']);
        });

        // Profil Bank Sampah - Format singkat (Route tambahan baru)
        Route::get('/bank-sampah/{id}', [ProfilBankSampahController::class, 'getBankSampah']);
        Route::get('/jam-operasional/{id}', [ProfilBankSampahController::class, 'getJamOperasional']);
        Route::get('/lokasi-bank/{id}', [ProfilBankSampahController::class, 'getLokasiBank']);
        Route::get('/kontak-bank/{id}', [ProfilBankSampahController::class, 'getKontakBank']);

        // Fitur Edukasi
        Route::prefix('edukasi')->group(function () {
            // Modul
            Route::get('/moduls', [EdukasiController::class, 'getModuls']);
            Route::get('/modul/{id}', [EdukasiController::class, 'getModulDetail']);

            // Konten Video
            Route::get('/video/{id}', [EdukasiController::class, 'getVideoDetail']);
            Route::post('/video/{id}/progress', [EdukasiController::class, 'updateVideoProgress']);

            // Konten Artikel
            Route::get('/artikel/{id}', [EdukasiController::class, 'getArtikelDetail']);
            Route::post('/artikel/{id}/progress', [EdukasiController::class, 'updateArtikelProgress']);

            // Poin dan Status
            Route::get('/points', [EdukasiController::class, 'getUserPoints']);
            Route::get('/auth-status', [EdukasiController::class, 'checkAuthStatus']);

            // Galeri Artikel
            Route::get('/artikel/{artikelId}/gallery', [EdukasiController::class, 'getArtikelGallery']);
        });

        // Jadwal Sampah
        Route::prefix('jadwal-sampah')->group(function () {
            Route::get('/', [JadwalSampahController::class, 'index']);
            Route::post('/by-tanggal', [JadwalSampahController::class, 'getJadwalByTanggal']);
            Route::post('/pemilahan', [JadwalSampahController::class, 'createPemilahanSchedule']);
            Route::post('/setoran', [JadwalSampahController::class, 'createSetoranSchedule']);
            Route::post('/mark-completed', [JadwalSampahController::class, 'markAsCompleted']);
            Route::post('/validate-setoran-date', [JadwalSampahController::class, 'validateSetoranDate']);
            Route::get('/{id}', [JadwalSampahController::class, 'show']);
            Route::put('/{id}', [JadwalSampahController::class, 'update']);
            Route::delete('/{id}', [JadwalSampahController::class, 'destroy']);
            Route::get('/check-registration', [JadwalSampahController::class, 'checkBankSampahRegistration']);
            Route::post('/calendar-view', [JadwalSampahController::class, 'getCalendarView']);
            Route::get('/bank-sampah-list', [JadwalSampahController::class, 'getNasabahBankSampahList']);
        });

        // Laporan Sampah (Fitur Baru)
        Route::prefix('laporan-sampah')->group(function () {
            Route::get('/bank-sampah-list', [LaporanSampahController::class, 'getBankSampahList']);
            Route::get('/check-nasabah-status', [LaporanSampahController::class, 'checkNasabahStatus']);
            Route::post('/summary', [LaporanSampahController::class, 'getLaporanSampahSummary']);

            // Endpoint untuk tab Tonase Sampah
            Route::post('/tonase-per-kategori', [LaporanSampahController::class, 'getTonaseSampahPerKategori']);
            Route::post('/tren-tonase', [LaporanSampahController::class, 'getTrenTonaseSampah']);
            Route::post('/riwayat-tonase', [LaporanSampahController::class, 'getRiwayatTonaseSampah']);

            // Endpoint untuk tab Penjualan Sampah
            Route::post('/penjualan-per-kategori', [LaporanSampahController::class, 'getPenjualanSampahPerKategori']);
            Route::post('/tren-penjualan', [LaporanSampahController::class, 'getTrenPenjualanSampah']);
            Route::post('/riwayat-penjualan', [LaporanSampahController::class, 'getRiwayatPenjualanSampah']);

            // Endpoint Opsional untuk Optimasi (Tambahan)
            Route::post('/dashboard', [LaporanSampahController::class, 'getDashboard']);
            Route::get('/detail-item/{id}', [LaporanSampahController::class, 'getDetailItem']);
            Route::get('/kode-warna-kategori', [LaporanSampahController::class, 'getKodeWarnaKategori']);
        });

        // Member Bank Sampah (sudah ada)
        Route::get('/member-bank-sampah', [MemberBankSampahController::class, 'getMemberBankSampah']);
        Route::get('/check-nasabah/{bankSampahId}', [MemberBankSampahController::class, 'checkNasabah']);
        Route::post('/register-member', [MemberBankSampahController::class, 'registerMember']);
        Route::delete('/remove-member/{bankSampahId}', [MemberBankSampahController::class, 'removeMember']);

        // Katalog Sampah
        Route::prefix('katalog-sampah')->group(function () {
            Route::post('/by-bank-sampah', [KatalogSampahController::class, 'getByBankSampah']);
            Route::get('/{id}', [KatalogSampahController::class, 'show']);
            Route::post('/search', [KatalogSampahController::class, 'search']);
            Route::post('/for-setoran', [KatalogSampahController::class, 'getForSetoran']);
            Route::post('/by-sub-kategori', [KatalogSampahController::class, 'getBySubKategori']);
            Route::post('/sub-kategori-by-kategori', [KatalogSampahController::class, 'getSubKategoriByKategori']);
        });

        // Setoran Sampah
        Route::prefix('setoran-sampah')->group(function () {
            Route::get('/', [SetoranSampahController::class, 'index']);
            Route::post('/', [SetoranSampahController::class, 'store']);
            Route::post('/pengajuan', [SetoranSampahController::class, 'createPengajuan']);
            Route::get('/member-bank-sampah', [SetoranSampahController::class, 'getMemberBankSampah']);
            Route::get('/history', [SetoranSampahController::class, 'history']);
            Route::get('/ongoing', [SetoranSampahController::class, 'ongoing']);
            Route::get('/statistics', [SetoranSampahController::class, 'statistics']);
            Route::get('/dashboard-stats', [SetoranSampahController::class, 'getDashboardStats']);
            Route::get('/{id}', [SetoranSampahController::class, 'show']);
            Route::get('/{id}/timeline', [SetoranSampahController::class, 'getStatusTimeline']);
            Route::post('/{id}/cancel', [SetoranSampahController::class, 'cancel']);
            Route::post('/{id}/update-status', [SetoranSampahController::class, 'updateStatus']);
        });

        // Detail Setoran
        Route::prefix('detail-setoran')->group(function () {
            Route::post('/', [DetailSetoranController::class, 'store']);
            Route::put('/{id}', [DetailSetoranController::class, 'update']);
            Route::delete('/{id}', [DetailSetoranController::class, 'destroy']);
            Route::post('/by-setoran', [DetailSetoranController::class, 'getBySetoran']);
            Route::get('/{id}/detail', [DetailSetoranController::class, 'getItemDetail']);
            Route::post('/bulk-update', [DetailSetoranController::class, 'bulkUpdate']);
        });

        // Sub Kategori Sampah
        Route::prefix('sub-kategori-sampah')->group(function () {
            Route::get('/kategori-utama', [SubKategoriSampahController::class, 'getKategoriUtama']);
            Route::post('/by-bank-sampah', [SubKategoriSampahController::class, 'getSubKategori']);
            Route::post('/katalog', [SubKategoriSampahController::class, 'getKatalogBySubKategori']);
            Route::post('/for-setoran', [SubKategoriSampahController::class, 'getKatalogForSetoran']);
        });
    });

    // Rute khusus bank sampah
    Route::prefix('bank-sampah')->group(function () {
        // Route untuk bank sampah akan ditambahkan nanti
    });

    // Rute khusus industri
    Route::prefix('industri')->group(function () {
        // Route untuk industri akan ditambahkan nanti
    });

    // Rute khusus pemerintah
    Route::prefix('pemerintah')->group(function () {
        // Route untuk pemerintah akan ditambahkan nanti
    });

    // Rute khusus admin
    Route::prefix('admin')->group(function () {
        // Edukasi Management
        Route::prefix('edukasi')->group(function () {
            // Upload hero image modul
            Route::post('/modul/{id}/hero-image', [EdukasiController::class, 'uploadModulHeroImage']);

            // Manajemen galeri artikel
            Route::post('/artikel/{artikelId}/gallery', [EdukasiController::class, 'uploadArtikelGallery']);
            Route::put('/artikel/{artikelId}/gallery/{galleryId}', [EdukasiController::class, 'updateArtikelGallery']);
            Route::delete('/artikel/{artikelId}/gallery/{galleryId}', [EdukasiController::class, 'deleteArtikelGallery']);
            Route::post('/artikel/{artikelId}/gallery/reorder', [EdukasiController::class, 'reorderArtikelGallery']);
        });
    });
});