<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Nasabah\ProfilNasabahController;

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
Route::post('/update-profile', [AuthController::class, 'updateProfile']);

// Rute yang memerlukan otentikasi
Route::middleware('auth:sanctum')->group(function () {
    // Rute untuk semua pengguna
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'getProfile']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);

    Route::get('/profile-status', [AuthController::class, 'checkProfileStatus']);

    // Rute khusus nasabah
    Route::prefix('nasabah')->middleware('role:nasabah')->group(function () {
        Route::get('/profile', [ProfilNasabahController::class, 'getProfile']);
        Route::post('/profile/step1', [ProfilNasabahController::class, 'updateProfileStep1']);
        Route::post('/profile/step2', [ProfilNasabahController::class, 'updateProfileStep2']);
        Route::post('/profile/step3', [ProfilNasabahController::class, 'updateProfileStep3']);
    });

    // Rute khusus bank sampah
    Route::prefix('bank-sampah')->middleware('role:mitra')->group(function () {
        // Route untuk bank sampah akan ditambahkan nanti
    });

    // Rute khusus industri
    Route::prefix('industri')->middleware('role:industri')->group(function () {
        // Route untuk industri akan ditambahkan nanti
    });

    // Rute khusus pemerintah
    Route::prefix('pemerintah')->middleware('role:pemerintah')->group(function () {
        // Route untuk pemerintah akan ditambahkan nanti
    });

    // Rute khusus admin
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        // Route untuk admin akan ditambahkan nanti
    });
});