<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\JadwalSampah;
use App\Models\MemberBankSampah;
use App\Models\TipeJadwalSampah;
use App\Models\BankSampah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JadwalSampahController extends Controller
{
    /**
     * Mendapatkan semua jadwal sampah nasabah.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userId = Auth::id();
        $jadwalSampah = JadwalSampah::where('user_id', $userId)
            ->with(['bankSampah', 'tipeJadwal'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jadwalSampah
        ]);
    }

    /**
     * Memeriksa status keanggotaan nasabah di bank sampah.
     *
     * @return \Illuminate\Http\Response
     */
    public function checkBankSampahRegistration()
    {
        $userId = Auth::id();

        // Cek apakah nasabah terdaftar di bank sampah
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => true,
                'is_registered' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah.',
                'condition' => 1 // Kondisi 1: Nasabah belum terdaftar di bank sampah
            ]);
        }

        // Cek apakah nasabah sudah membuat jadwal pemilahan
        $existingJadwalPemilahan = JadwalSampah::where('user_id', $userId)
            ->where('tipe_jadwal_id', TipeJadwalSampah::PEMILAHAN)
            ->first();

        // Cek apakah nasabah sudah membuat jadwal rencana setoran
        $existingJadwalSetoran = JadwalSampah::where('user_id', $userId)
            ->where('tipe_jadwal_id', TipeJadwalSampah::RENCANA_SETORAN)
            ->first();

        if (!$existingJadwalPemilahan && !$existingJadwalSetoran) {
            return response()->json([
                'success' => true,
                'is_registered' => true,
                'has_schedule' => false,
                'message' => 'Anda sudah terdaftar sebagai nasabah bank sampah, tetapi belum membuat jadwal.',
                'bank_sampah' => $memberBankSampah->bankSampah,
                'condition' => 2 // Kondisi 2: Nasabah terdaftar tetapi belum ada jadwal
            ]);
        }

        return response()->json([
            'success' => true,
            'is_registered' => true,
            'has_schedule' => true,
            'message' => 'Anda sudah terdaftar sebagai nasabah bank sampah dan sudah membuat jadwal.',
            'bank_sampah' => $memberBankSampah->bankSampah,
            'has_pemilahan_schedule' => $existingJadwalPemilahan ? true : false,
            'has_setoran_schedule' => $existingJadwalSetoran ? true : false,
            'condition' => 3 // Kondisi 3: Nasabah terdaftar dan sudah ada jadwal
        ]);
    }

    /**
     * Mendapatkan jadwal sampah untuk tampilan kalender.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getCalendarView(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|min:2023',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();

        // Ambil semua jadwal dalam rentang bulan yang diminta
        $jadwalSampah = JadwalSampah::where('user_id', $userId)
            ->whereBetween('tanggal_mulai', [$startDate, $endDate])
            ->with(['bankSampah', 'tipeJadwal'])
            ->get();

        // Kelompokkan jadwal berdasarkan tanggal
        $calendarData = [];

        foreach ($jadwalSampah as $jadwal) {
            $tanggal = Carbon::parse($jadwal->tanggal_mulai)->format('Y-m-d');

            if (!isset($calendarData[$tanggal])) {
                $calendarData[$tanggal] = [];
            }

            // Tambahkan warna berdasarkan tipe jadwal
            $color = 'blue'; // Default for pemilahan
            if ($jadwal->tipe_jadwal_id == TipeJadwalSampah::RENCANA_SETORAN) {
                $color = 'green';
            } elseif ($jadwal->tipe_jadwal_id == TipeJadwalSampah::SETORAN) {
                $color = 'orange';
            }

            // Buat nama jadwal dengan format sesuai
            $namaJadwal = $jadwal->tipeJadwal->nama . ' #' . $jadwal->nomor_urut;

            $calendarData[$tanggal][] = [
                'id' => $jadwal->id,
                'title' => $namaJadwal,
                'tipe_jadwal_id' => $jadwal->tipe_jadwal_id,
                'tipe_jadwal_nama' => $jadwal->tipeJadwal->nama,
                'waktu' => $jadwal->waktu_mulai,
                'status' => $jadwal->status,
                'color' => $color,
                'nomor_urut' => $jadwal->nomor_urut
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'bulan' => $bulan,
            'tahun' => $tahun
        ]);
    }

    /**
     * Mendapatkan daftar bank sampah yang terdaftar oleh nasabah.
     *
     * @return \Illuminate\Http\Response
     */
    public function getNasabahBankSampahList()
    {
        $userId = Auth::id();

        $bankSampahList = MemberBankSampah::where('user_id', $userId)
            ->with('bankSampah')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->bankSampah->id,
                    'nama' => $member->bankSampah->nama,
                    'alamat' => $member->bankSampah->alamat,
                    'tanggal_setoran' => $member->bankSampah->tanggal_setoran,
                    'member_id' => $member->id,
                    'tanggal_bergabung' => $member->created_at
                ];
            });

        if ($bankSampahList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar di bank sampah manapun.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bankSampahList
        ]);
    }

    /**
     * Mendapatkan jadwal sampah berdasarkan tanggal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getJadwalByTanggal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $tanggal = $request->tanggal;

        $jadwalSampah = JadwalSampah::where('user_id', $userId)
            ->where('tanggal_mulai', $tanggal)
            ->with(['bankSampah', 'tipeJadwal'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jadwalSampah
        ]);
    }

    /**
     * Menyimpan jadwal pemilahan sampah baru.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createPemilahanSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'frekuensi' => 'required|in:harian,mingguan,bulanan',
            'waktu_mulai' => 'required|date_format:H:i',
            'tanggal_mulai' => 'required|date_format:Y-m-d|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();

        // Cek apakah nasabah sudah pernah membuat jadwal pemilahan
        $existingJadwalPemilahan = JadwalSampah::where('user_id', $userId)
            ->where('tipe_jadwal_id', TipeJadwalSampah::PEMILAHAN)
            ->first();

        if ($existingJadwalPemilahan) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki jadwal pemilahan sampah.'
            ], 422);
        }

        // Cek apakah nasabah terdaftar di bank sampah
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)->first();
        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah.'
            ], 422);
        }

        // Hitung nomor urut
        $nomorUrut = 1;
        $lastJadwalPemilahan = JadwalSampah::where('user_id', $userId)
            ->where('tipe_jadwal_id', TipeJadwalSampah::PEMILAHAN)
            ->orderBy('nomor_urut', 'desc')
            ->first();

        if ($lastJadwalPemilahan) {
            $nomorUrut = $lastJadwalPemilahan->nomor_urut + 1;
        }

        // Buat jadwal pemilahan baru
        $jadwalPemilahan = new JadwalSampah();
        $jadwalPemilahan->user_id = $userId;
        $jadwalPemilahan->bank_sampah_id = $memberBankSampah->bank_sampah_id;
        $jadwalPemilahan->tipe_jadwal_id = TipeJadwalSampah::PEMILAHAN;
        $jadwalPemilahan->frekuensi = $request->frekuensi;
        $jadwalPemilahan->waktu_mulai = $request->waktu_mulai;
        $jadwalPemilahan->tanggal_mulai = $request->tanggal_mulai;
        $jadwalPemilahan->status = 'belum selesai';
        $jadwalPemilahan->nomor_urut = $nomorUrut;
        $jadwalPemilahan->save();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal pemilahan sampah berhasil dibuat',
            'data' => $jadwalPemilahan
        ]);
    }

    /**
     * Menyimpan jadwal rencana setoran sampah baru.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createSetoranSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'waktu_mulai' => 'required|date_format:H:i',
            'tanggal_mulai' => 'required|date_format:Y-m-d|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini.'
            ], 422);
        }

        // Validasi tanggal setoran (H-3)
        $bankSampah = BankSampah::find($bankSampahId);
        $tanggalSetoran = Carbon::parse($bankSampah->tanggal_setoran);
        $tanggalMulai = Carbon::parse($request->tanggal_mulai);

        if ($tanggalMulai->diffInDays($tanggalSetoran) > 3) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal rencana setoran tidak boleh lebih dari 3 hari sebelum jadwal setoran bank sampah.'
            ], 422);
        }

        // Hitung nomor urut
        $nomorUrut = 1;
        $lastJadwalSetoran = JadwalSampah::where('user_id', $userId)
            ->where('tipe_jadwal_id', TipeJadwalSampah::RENCANA_SETORAN)
            ->where('bank_sampah_id', $bankSampahId)
            ->orderBy('nomor_urut', 'desc')
            ->first();

        if ($lastJadwalSetoran) {
            $nomorUrut = $lastJadwalSetoran->nomor_urut + 1;
        }

        // Buat jadwal rencana setoran baru
        $jadwalSetoran = new JadwalSampah();
        $jadwalSetoran->user_id = $userId;
        $jadwalSetoran->bank_sampah_id = $bankSampahId;
        $jadwalSetoran->tipe_jadwal_id = TipeJadwalSampah::RENCANA_SETORAN;
        $jadwalSetoran->frekuensi = null; // Rencana setoran tidak memerlukan frekuensi
        $jadwalSetoran->waktu_mulai = $request->waktu_mulai;
        $jadwalSetoran->tanggal_mulai = $request->tanggal_mulai;
        $jadwalSetoran->status = 'belum selesai';
        $jadwalSetoran->nomor_urut = $nomorUrut;
        $jadwalSetoran->save();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal rencana setoran sampah berhasil dibuat',
            'data' => $jadwalSetoran
        ]);
    }

    /**
     * Menampilkan detail jadwal sampah.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::id();
        $jadwalSampah = JadwalSampah::where('id', $id)
            ->where('user_id', $userId)
            ->with(['bankSampah', 'tipeJadwal'])
            ->first();

        if (!$jadwalSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal sampah tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $jadwalSampah
        ]);
    }

    /**
     * Menandai jadwal sampah sebagai selesai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAsCompleted(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jadwal_sampah_id' => 'required|exists:jadwal_sampah,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $jadwalSampahId = $request->jadwal_sampah_id;

        $jadwalSampah = JadwalSampah::where('id', $jadwalSampahId)
            ->where('user_id', $userId)
            ->first();

        if (!$jadwalSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal sampah tidak ditemukan'
            ], 404);
        }

        if ($jadwalSampah->status === 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal sampah sudah ditandai selesai sebelumnya'
            ], 422);
        }

        $jadwalSampah->status = 'selesai';
        $jadwalSampah->save();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal sampah berhasil ditandai selesai',
            'data' => $jadwalSampah
        ]);
    }

    /**
     * Update jadwal sampah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'waktu_mulai' => 'sometimes|required|date_format:H:i',
            'tanggal_mulai' => 'sometimes|required|date_format:Y-m-d|after:today',
            'frekuensi' => 'sometimes|required|in:harian,mingguan,bulanan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $jadwalSampah = JadwalSampah::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$jadwalSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal sampah tidak ditemukan'
            ], 404);
        }

        // Jika jadwal adalah rencana setoran, validasi tanggal (H-3)
        if ($jadwalSampah->tipe_jadwal_id == TipeJadwalSampah::RENCANA_SETORAN && $request->has('tanggal_mulai')) {
            $bankSampah = BankSampah::find($jadwalSampah->bank_sampah_id);
            $tanggalSetoran = Carbon::parse($bankSampah->tanggal_setoran);
            $tanggalMulai = Carbon::parse($request->tanggal_mulai);

            if ($tanggalMulai->diffInDays($tanggalSetoran) > 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal rencana setoran tidak boleh lebih dari 3 hari sebelum jadwal setoran bank sampah.'
                ], 422);
            }
        }

        if ($request->has('waktu_mulai')) {
            $jadwalSampah->waktu_mulai = $request->waktu_mulai;
        }

        if ($request->has('tanggal_mulai')) {
            $jadwalSampah->tanggal_mulai = $request->tanggal_mulai;
        }

        if ($request->has('frekuensi') && $jadwalSampah->tipe_jadwal_id == TipeJadwalSampah::PEMILAHAN) {
            $jadwalSampah->frekuensi = $request->frekuensi;
        }

        $jadwalSampah->save();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal sampah berhasil diupdate',
            'data' => $jadwalSampah
        ]);
    }

    /**
     * Menghapus jadwal sampah.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $userId = Auth::id();
        $jadwalSampah = JadwalSampah::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$jadwalSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal sampah tidak ditemukan'
            ], 404);
        }

        $jadwalSampah->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal sampah berhasil dihapus'
        ]);
    }

    /**
     * Validasi tanggal rencana setoran.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validateSetoranDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'tanggal_mulai' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $bankSampahId = $request->bank_sampah_id;
        $tanggalMulai = Carbon::parse($request->tanggal_mulai);

        // Dapatkan tanggal setoran bank sampah
        $bankSampah = BankSampah::find($bankSampahId);
        $tanggalSetoran = Carbon::parse($bankSampah->tanggal_setoran);

        // Validasi tanggal (H-3)
        $isValid = $tanggalMulai->diffInDays($tanggalSetoran) <= 3;

        return response()->json([
            'success' => true,
            'is_valid' => $isValid,
            'message' => $isValid ? 'Tanggal valid' : 'Tanggal tidak valid, harus maksimal 3 hari sebelum tanggal setoran bank sampah'
        ]);
    }
}