<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Nasabah\ProfilNasabahController;
use App\Http\Controllers\API\Nasabah\EdukasiController;
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

        // Fitur Edukasi
        Route::prefix('edukasi')->group(function () {
            // Modul
            Route::get('/moduls', [EdukasiController::class, 'getModuls']);
            Route::get('/moduls/{id}', [EdukasiController::class, 'getModulDetail']);

            // Konten Video
            Route::get('/videos/{id}', [EdukasiController::class, 'getVideoDetail']);
            Route::post('/videos/{id}/progress', [EdukasiController::class, 'updateVideoProgress']);

            // Konten Artikel
            Route::get('/artikels/{id}', [EdukasiController::class, 'getArtikelDetail']);
            Route::post('/artikels/{id}/progress', [EdukasiController::class, 'updateArtikelProgress']);
        });

        // Jadwal Sampah
        Route::prefix('jadwal-sampah')->group(function () {
            Route::get('/', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'index']);
            Route::post('/by-tanggal', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'getJadwalByTanggal']);
            Route::post('/pemilahan', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'createPemilahanSchedule']);
            Route::post('/setoran', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'createSetoranSchedule']);
            Route::post('/mark-completed', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'markAsCompleted']);
            Route::post('/validate-setoran-date', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'validateSetoranDate']);
            Route::get('/{id}', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'destroy']);
            Route::get('/check-registration', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'checkBankSampahRegistration']);
            Route::post('/calendar-view', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'getCalendarView']);
            Route::get('/bank-sampah-list', [App\Http\Controllers\API\Nasabah\JadwalSampahController::class, 'getNasabahBankSampahList']);
        });

        // Member Bank Sampah (dipindahkan keluar dari jadwal-sampah)
        Route::get('/member-bank-sampah', [App\Http\Controllers\API\Nasabah\MemberBankSampahController::class, 'getMemberBankSampah']);
        Route::get('/check-nasabah/{bankSampahId}', [App\Http\Controllers\API\Nasabah\MemberBankSampahController::class, 'checkNasabah']);
        Route::post('/register-member', [App\Http\Controllers\API\Nasabah\MemberBankSampahController::class, 'registerMember']);
        Route::delete('/remove-member/{bankSampahId}', [App\Http\Controllers\API\Nasabah\MemberBankSampahController::class, 'removeMember']);

        // Profil Bank Sampah (dipindahkan keluar dari jadwal-sampah)
        Route::get('/bank-sampah/{id}', [App\Http\Controllers\API\Nasabah\ProfilBankSampahController::class, 'getBankSampah']);
        Route::get('/katalog-sampah/{bankSampahId}', [App\Http\Controllers\API\Nasabah\ProfilBankSampahController::class, 'getKatalogSampah']);
        Route::get('/jam-operasional/{bankSampahId}', [App\Http\Controllers\API\Nasabah\ProfilBankSampahController::class, 'getJamOperasional']);
        Route::get('/lokasi-bank/{bankSampahId}', [App\Http\Controllers\API\Nasabah\ProfilBankSampahController::class, 'getLokasiBank']);
        Route::get('/kontak-bank/{bankSampahId}', [App\Http\Controllers\API\Nasabah\ProfilBankSampahController::class, 'getKontakBank']);
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
        // Route untuk admin akan ditambahkan nanti
    });
});
