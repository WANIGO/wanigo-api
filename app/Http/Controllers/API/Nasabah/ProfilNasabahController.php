<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Nasabah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ProfilNasabahController extends Controller
{
    /**
     * Mendapatkan data profil nasabah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $user->load('nasabah');

        return response()->json([
            'status' => 'success',
            'message' => 'Profil nasabah berhasil diambil',
            'data' => [
                'user' => $user,
                'nasabah' => $user->nasabah,
                'profile_status' => $user->getProfileStatus(),
                'profile_completion' => $user->nasabah ? $user->nasabah->getProfileCompletionPercentage() : 0,
                'next_step' => $user->nasabah ? $user->nasabah->getNextStep() : 'step1',
            ]
        ]);
    }

    /**
     * Mengupdate profil nasabah tahap 1.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfileStep1(Request $request)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'jenis_kelamin' => 'required|string|in:Laki-laki,Perempuan',
            'usia' => 'required|string|in:Dibawah 18 tahun,18 hingga 34 tahun,34 hingga 54 tahun,Di atas 54 tahun',
            'profesi' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nasabah = $user->nasabah;
        if (!$nasabah) {
            $nasabah = Nasabah::create([
                'user_id' => $user->id,
                'jenis_kelamin' => $request->jenis_kelamin,
                'usia' => $request->usia,
                'profesi' => $request->profesi,
            ]);
        } else {
            $nasabah->update([
                'jenis_kelamin' => $request->jenis_kelamin,
                'usia' => $request->usia,
                'profesi' => $request->profesi,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profil nasabah tahap 1 berhasil diupdate',
            'data' => [
                'nasabah' => $nasabah,
                'profile_status' => $user->getProfileStatus(),
                'profile_completion' => $nasabah->getProfileCompletionPercentage(),
                'next_step' => $nasabah->getNextStep(),
            ]
        ]);
    }

    /**
     * Mengupdate profil nasabah tahap 2.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfileStep2(Request $request)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $nasabah = $user->nasabah;
        if (!$nasabah || !$nasabah->isPartOneComplete()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda harus menyelesaikan profil tahap 1 terlebih dahulu',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tahu_memilah_sampah' => 'required|string|in:Sudah tahu,Belum tahu',
            'motivasi_memilah_sampah' => 'required|string|in:Menghasilkan uang,Menjaga lingkungan',
            'nasabah_bank_sampah' => 'required|string|in:Iya, sudah,Tidak, belum',
            'kode_bank_sampah' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nasabah->update([
            'tahu_memilah_sampah' => $request->tahu_memilah_sampah,
            'motivasi_memilah_sampah' => $request->motivasi_memilah_sampah,
            'nasabah_bank_sampah' => $request->nasabah_bank_sampah,
            'kode_bank_sampah' => $request->kode_bank_sampah,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil nasabah tahap 2 berhasil diupdate',
            'data' => [
                'nasabah' => $nasabah,
                'profile_status' => $user->getProfileStatus(),
                'profile_completion' => $nasabah->getProfileCompletionPercentage(),
                'next_step' => $nasabah->getNextStep(),
            ]
        ]);
    }

    /**
     * Mengupdate profil nasabah tahap 3.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateProfileStep3(Request $request)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $nasabah = $user->nasabah;
        if (!$nasabah || !$nasabah->isPartTwoComplete()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda harus menyelesaikan profil tahap 2 terlebih dahulu',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'frekuensi_memilah_sampah' => 'required|string|in:Setiap hari,Setiap minggu,Setiap bulan,Sangat jarang',
            'jenis_sampah_dikelola' => 'required|string|in:Plastik,Kertas/Kardus,Kaca/Logam,Elektronik,Organik,Lainnya',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nasabah->update([
            'frekuensi_memilah_sampah' => $request->frekuensi_memilah_sampah,
            'jenis_sampah_dikelola' => $request->jenis_sampah_dikelola,
            'profile_completed_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil nasabah tahap 3 berhasil diupdate',
            'data' => [
                'nasabah' => $nasabah,
                'profile_status' => $user->getProfileStatus(),
                'profile_completion' => $nasabah->getProfileCompletionPercentage(),
                'next_step' => $nasabah->getNextStep(),
            ]
        ]);
    }
}