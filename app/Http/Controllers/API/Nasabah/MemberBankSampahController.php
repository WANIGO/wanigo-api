<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\BankSampah;
use App\Models\MemberBankSampah;
use App\Models\SetoranSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MemberBankSampahController extends Controller
{
    /**
     * Mendapatkan daftar bank sampah yang terdaftar oleh pengguna.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getMemberBankSampah(Request $request)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        // Cek apakah user terdaftar di bank sampah
        $memberList = MemberBankSampah::where('user_id', $user->id)->get();

        if ($memberList->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak terdaftar di bank sampah manapun',
                'data' => [
                    'is_registered' => false,
                    'bank_sampah' => []
                ]
            ]);
        }

        // Jika terdaftar, ambil data bank sampah
        $bankSampahIds = $memberList->pluck('bank_sampah_id')->toArray();
        $bankSampahList = BankSampah::with('jamOperasional')->whereIn('id', $bankSampahIds)->get();

        // Tambahkan informasi keanggotaan dan status operasional
        $bankSampahList->map(function($item) use ($memberList) {
            $member = $memberList->where('bank_sampah_id', $item->id)->first();
            $item->kode_nasabah = $member ? $member->kode_nasabah : null;
            $item->tanggal_bergabung = $member ? $member->tanggal_daftar : null;
            $item->status_operasional = $item->isBukaHariIni() ? 'Aktif' : 'Tutup';

            // Tambahkan status keanggotaan
            $item->status_keanggotaan = $member ? $member->status_keanggotaan : null;

            return $item;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar bank sampah berhasil diambil',
            'data' => [
                'is_registered' => true,
                'bank_sampah' => $bankSampahList
            ]
        ]);
    }

    /**
     * Mengecek status keanggotaan pengguna di bank sampah tertentu.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function checkNasabah(Request $request, $bankSampahId)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $member = MemberBankSampah::where('user_id', $user->id)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$member) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum terdaftar di bank sampah ini',
                'data' => [
                    'is_registered' => false
                ]
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sudah terdaftar di bank sampah ini',
            'data' => [
                'is_registered' => true,
                'kode_nasabah' => $member->kode_nasabah,
                'status' => $member->status_keanggotaan,
                'tanggal_bergabung' => $member->tanggal_daftar
            ]
        ]);
    }

    /**
     * Mendaftarkan pengguna ke bank sampah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function registerMember(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->isNasabah()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Anda bukan nasabah.',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'bank_sampah_id' => 'required|exists:bank_sampah,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Cek apakah sudah terdaftar
            $existingMember = MemberBankSampah::where('user_id', $user->id)
                ->where('bank_sampah_id', $request->bank_sampah_id)
                ->first();

            if ($existingMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda sudah terdaftar di bank sampah ini',
                    'data' => [
                        'kode_nasabah' => $existingMember->kode_nasabah
                    ]
                ], 422);
            }

            // Ambil data bank sampah
            $bankSampah = BankSampah::find($request->bank_sampah_id);
            if (!$bankSampah) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bank sampah tidak ditemukan',
                ], 404);
            }

            // Generate kode nasabah unik
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $bankSampah->nama_bank_sampah), 0, 3));
            $randomCode = strtoupper(Str::random(3));
            $timestamp = Carbon::now()->format('ymd');
            $kodeNasabah = $prefix . $timestamp . $randomCode;

            // Buat member baru sesuai dengan struktur tabel
            $member = MemberBankSampah::create([
                'user_id' => $user->id,
                'bank_sampah_id' => $request->bank_sampah_id,
                'kode_nasabah' => $kodeNasabah,
                'tanggal_daftar' => Carbon::now()->toDateString(),
                'status_keanggotaan' => 'aktif',
                'saldo' => 0.00
            ]);

            // Update jumlah nasabah di bank sampah
            $this->updateJumlahNasabah($bankSampah);

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil mendaftar sebagai nasabah bank sampah',
                'data' => [
                    'member' => $member,
                    'kode_nasabah' => $kodeNasabah,
                    'bank_sampah' => $bankSampah
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada registerMember: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server',
                'debug_message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus keanggotaan pengguna dari bank sampah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function removeMember(Request $request, $bankSampahId)
    {
        try {
            $user = $request->user();

            if (!$user->isNasabah()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Anda bukan nasabah.',
                ], 403);
            }

            // Cek apakah terdaftar
            $member = MemberBankSampah::where('user_id', $user->id)
                ->where('bank_sampah_id', $bankSampahId)
                ->first();

            if (!$member) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak terdaftar di bank sampah ini',
                ], 404);
            }

            // Cek apakah ada setoran aktif
            $hasActiveTransaction = $this->hasActiveTransactions($member);
            if ($hasActiveTransaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak dapat berhenti sebagai nasabah karena masih memiliki setoran aktif',
                ], 422);
            }

            // Hapus keanggotaan
            $member->delete();

            // Update jumlah nasabah di bank sampah
            $bankSampah = BankSampah::find($bankSampahId);
            if ($bankSampah) {
                $this->updateJumlahNasabah($bankSampah);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil berhenti sebagai nasabah bank sampah',
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada removeMember: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server',
                'debug_message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memeriksa apakah member memiliki transaksi aktif.
     *
     * @param  \App\Models\MemberBankSampah  $member
     * @return bool
     */
    private function hasActiveTransactions($member)
    {
        // Mengecek apakah ada setoran sampah dengan status yang masih aktif
        $activeTransactions = SetoranSampah::where('user_id', $member->user_id)
            ->where('bank_sampah_id', $member->bank_sampah_id)
            ->whereIn('status_setoran', ['Pengajuan', 'Diproses'])
            ->count();

        return $activeTransactions > 0;
    }

    /**
     * Memperbarui jumlah nasabah di bank sampah.
     *
     * @param  \App\Models\BankSampah  $bankSampah
     * @return void
     */
    private function updateJumlahNasabah($bankSampah)
    {
        $jumlahNasabah = MemberBankSampah::where('bank_sampah_id', $bankSampah->id)
            ->where('status_keanggotaan', 'aktif')
            ->count();

        $bankSampah->jumlah_nasabah = $jumlahNasabah;
        $bankSampah->save();
    }
}