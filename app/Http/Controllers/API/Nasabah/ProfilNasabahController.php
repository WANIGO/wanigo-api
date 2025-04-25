<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Nasabah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
                'form_options' => [
                    'jenis_kelamin' => ['Laki-laki', 'Perempuan'],
                    'usia' => ['Dibawah 18 tahun', '18 hingga 34 tahun', '34 hingga 54 tahun', 'Di atas 54 tahun'],
                    'tahu_memilah_sampah' => ['Sudah tahu', 'Belum tahu'],
                    'motivasi_memilah_sampah' => ['Menghasilkan uang', 'Menjaga lingkungan'],
                    'nasabah_bank_sampah' => ['Iya, sudah', 'Tidak, belum'],
                    'frekuensi_memilah_sampah' => ['Setiap hari', 'Setiap minggu', 'Setiap bulan', 'Sangat jarang'],
                    'jenis_sampah_dikelola' => ['Plastik', 'Kertas/Kardus', 'Kaca/Logam', 'Elektronik', 'Organik', 'Lainnya'],
                ]
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
            'jenis_kelamin' => 'required|string',
            'usia' => 'required|string',
            'profesi' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
                'valid_options' => [
                    'jenis_kelamin' => ['Laki-laki', 'Perempuan'],
                    'usia' => ['Dibawah 18 tahun', '18 hingga 34 tahun', '34 hingga 54 tahun', 'Di atas 54 tahun'],
                ]
            ], 422);
        }

        // Validasi tambahan untuk nilai yang valid
        $jenis_kelamin_valid = in_array($request->jenis_kelamin, ['Laki-laki', 'Perempuan']);
        $usia_valid = in_array($request->usia, ['Dibawah 18 tahun', '18 hingga 34 tahun', '34 hingga 54 tahun', 'Di atas 54 tahun']);

        if (!$jenis_kelamin_valid || !$usia_valid) {
            $errors = [];

            if (!$jenis_kelamin_valid) {
                $errors['jenis_kelamin'] = ['Pilihan jenis kelamin tidak valid.'];
            }

            if (!$usia_valid) {
                $errors['usia'] = ['Pilihan usia tidak valid.'];
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $errors,
                'valid_options' => [
                    'jenis_kelamin' => ['Laki-laki', 'Perempuan'],
                    'usia' => ['Dibawah 18 tahun', '18 hingga 34 tahun', '34 hingga 54 tahun', 'Di atas 54 tahun'],
                ]
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
            'tahu_memilah_sampah' => 'required|string',
            'motivasi_memilah_sampah' => 'required|string',
            'nasabah_bank_sampah' => 'required|string',
            'kode_bank_sampah' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
                'valid_options' => [
                    'tahu_memilah_sampah' => ['Sudah tahu', 'Belum tahu'],
                    'motivasi_memilah_sampah' => ['Menghasilkan uang', 'Menjaga lingkungan'],
                    'nasabah_bank_sampah' => ['Iya, sudah', 'Tidak, belum'],
                ]
            ], 422);
        }

        // Log nilai yang diterima untuk debugging
        Log::info('Nilai yang diterima untuk nasabah_bank_sampah: "' . $request->nasabah_bank_sampah . '"');

        // Normalisasi nilai nasabah_bank_sampah untuk mengatasi masalah spasi
        $nasabah_bank_sampah = $request->nasabah_bank_sampah;
        if ($nasabah_bank_sampah == "Tidak,belum") {
            $nasabah_bank_sampah = "Tidak, belum";
        } else if ($nasabah_bank_sampah == "Iya,sudah") {
            $nasabah_bank_sampah = "Iya, sudah";
        }

        // Validasi nilai-nilai yang diterima
        $valid_tahu_memilah = in_array($request->tahu_memilah_sampah, ['Sudah tahu', 'Belum tahu']);
        $valid_motivasi = in_array($request->motivasi_memilah_sampah, ['Menghasilkan uang', 'Menjaga lingkungan']);
        $valid_nasabah_bank = in_array($nasabah_bank_sampah, ['Iya, sudah', 'Tidak, belum']);

        if (!$valid_tahu_memilah || !$valid_motivasi || !$valid_nasabah_bank) {
            $errors = [];

            if (!$valid_tahu_memilah) {
                $errors['tahu_memilah_sampah'] = ['Pilihan tahu memilah sampah tidak valid.'];
            }

            if (!$valid_motivasi) {
                $errors['motivasi_memilah_sampah'] = ['Pilihan motivasi memilah sampah tidak valid.'];
            }

            if (!$valid_nasabah_bank) {
                $errors['nasabah_bank_sampah'] = ['The selected nasabah bank sampah is invalid.'];
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $errors,
                'valid_options' => [
                    'tahu_memilah_sampah' => ['Sudah tahu', 'Belum tahu'],
                    'motivasi_memilah_sampah' => ['Menghasilkan uang', 'Menjaga lingkungan'],
                    'nasabah_bank_sampah' => ['Iya, sudah', 'Tidak, belum'],
                    'received_value' => $request->nasabah_bank_sampah, // Untuk debugging
                ]
            ], 422);
        }

        $nasabah->update([
            'tahu_memilah_sampah' => $request->tahu_memilah_sampah,
            'motivasi_memilah_sampah' => $request->motivasi_memilah_sampah,
            'nasabah_bank_sampah' => $nasabah_bank_sampah, // Gunakan nilai yang sudah dinormalisasi
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
            'frekuensi_memilah_sampah' => 'required|string',
            'jenis_sampah_dikelola' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
                'valid_options' => [
                    'frekuensi_memilah_sampah' => ['Setiap hari', 'Setiap minggu', 'Setiap bulan', 'Sangat jarang'],
                    'jenis_sampah_dikelola' => ['Plastik', 'Kertas/Kardus', 'Kaca/Logam', 'Elektronik', 'Organik', 'Lainnya'],
                ]
            ], 422);
        }

        // Validasi tambahan untuk nilai yang valid
        $valid_frekuensi = in_array($request->frekuensi_memilah_sampah, ['Setiap hari', 'Setiap minggu', 'Setiap bulan', 'Sangat jarang']);
        $valid_jenis = in_array($request->jenis_sampah_dikelola, ['Plastik', 'Kertas/Kardus', 'Kaca/Logam', 'Elektronik', 'Organik', 'Lainnya']);

        if (!$valid_frekuensi || !$valid_jenis) {
            $errors = [];

            if (!$valid_frekuensi) {
                $errors['frekuensi_memilah_sampah'] = ['Pilihan frekuensi memilah sampah tidak valid.'];
            }

            if (!$valid_jenis) {
                $errors['jenis_sampah_dikelola'] = ['Pilihan jenis sampah dikelola tidak valid.'];
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $errors,
                'valid_options' => [
                    'frekuensi_memilah_sampah' => ['Setiap hari', 'Setiap minggu', 'Setiap bulan', 'Sangat jarang'],
                    'jenis_sampah_dikelola' => ['Plastik', 'Kertas/Kardus', 'Kaca/Logam', 'Elektronik', 'Organik', 'Lainnya'],
                ]
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