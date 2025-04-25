<?php

namespace App\Http\Controllers\API\Nasabah;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\SetoranSampah;
use App\Models\SetoranSampahLog;
use App\Models\DetailSetoran;
use App\Models\KatalogSampah;
use App\Models\MemberBankSampah;
use App\Models\BankSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SetoranSampahController extends Controller
{
    /**
     * Mendapatkan daftar setoran sampah milik nasabah.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = SetoranSampah::where('user_id', $userId)
            ->with(['bankSampah', 'detailSetoran']);

        // Filter berdasarkan status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan bank sampah
        if ($request->has('bank_sampah_id')) {
            $query->where('bank_sampah_id', $request->bank_sampah_id);
        }

        // Filter berdasarkan tanggal
        if ($request->has('tanggal_mulai') && $request->has('tanggal_akhir')) {
            $query->whereBetween('tanggal_setoran', [$request->tanggal_mulai, $request->tanggal_akhir]);
        } elseif ($request->has('tanggal_mulai')) {
            $query->where('tanggal_setoran', '>=', $request->tanggal_mulai);
        } elseif ($request->has('tanggal_akhir')) {
            $query->where('tanggal_setoran', '<=', $request->tanggal_akhir);
        }

        $setoran = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $setoran
        ]);
    }

    /**
     * Membuat setoran sampah baru.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'tanggal_setoran' => 'required|date_format:Y-m-d',
            'waktu_setoran' => 'required|date_format:H:i',
            'detail_setoran' => 'required|array',
            'detail_setoran.*.katalog_sampah_id' => 'required|exists:katalog_sampah,id',
            'detail_setoran.*.berat' => 'required|numeric|min:0.1',
            'detail_setoran.*.foto' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'catatan' => 'sometimes|string|max:255',
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
            ->where('status_keanggotaan', 'aktif')
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini atau status keanggotaan tidak aktif'
            ], 422);
        }

        // Cek apakah tanggal setoran valid dengan jadwal bank sampah
        $bankSampah = BankSampah::find($bankSampahId);
        $tanggalSetoran = Carbon::parse($request->tanggal_setoran);

        if ($bankSampah->tanggal_setoran && !$this->isTanggalSetoranValid($tanggalSetoran, $bankSampah->tanggal_setoran)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal setoran tidak sesuai dengan jadwal setoran bank sampah'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Buat record setoran baru
            $kodeSetoran = $this->generateKodeSetoran();
            $setoran = new SetoranSampah();
            $setoran->kode_setoran = $kodeSetoran;
            $setoran->user_id = $userId;
            $setoran->bank_sampah_id = $bankSampahId;
            $setoran->tanggal_setoran = $request->tanggal_setoran;
            $setoran->waktu_setoran = $request->waktu_setoran;
            $setoran->status = 'menunggu_konfirmasi';
            $setoran->catatan = $request->catatan;
            $setoran->total_berat = 0; // Akan diupdate setelah detail diproses
            $setoran->total_nilai = 0; // Akan diupdate setelah detail diproses
            $setoran->save();

            // Buat log status
            $this->createSetoranLog($setoran->id, 'menunggu_konfirmasi', 'Pengajuan setoran dibuat');

            // Proses detail setoran
            $totalBerat = 0;
            $totalNilai = 0;

            foreach ($request->detail_setoran as $detail) {
                $katalogSampah = KatalogSampah::find($detail['katalog_sampah_id']);

                if (!$katalogSampah || $katalogSampah->bank_sampah_id != $bankSampahId) {
                    throw new \Exception('Katalog sampah tidak valid atau tidak terdaftar di bank sampah ini');
                }

                $detailSetoran = new DetailSetoran();
                $detailSetoran->setoran_sampah_id = $setoran->id;
                $detailSetoran->katalog_sampah_id = $detail['katalog_sampah_id'];
                $detailSetoran->berat = $detail['berat'];
                $detailSetoran->harga_per_kg = $katalogSampah->harga_per_kg;
                $detailSetoran->nilai = $detail['berat'] * $katalogSampah->harga_per_kg;

                // Proses foto jika ada
                if (isset($detail['foto']) && $detail['foto']) {
                    $path = $detail['foto']->store('setoran_sampah', 'public');
                    $detailSetoran->foto = $path;
                }

                $detailSetoran->save();

                $totalBerat += $detail['berat'];
                $totalNilai += $detailSetoran->nilai;
            }

            // Update total berat dan nilai setoran
            $setoran->total_berat = $totalBerat;
            $setoran->total_nilai = $totalNilai;
            $setoran->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Setoran sampah berhasil dibuat dan menunggu konfirmasi dari bank sampah',
                'data' => $setoran->load('detailSetoran')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat setoran sampah: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan detail setoran sampah.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::id();
        $setoran = SetoranSampah::where('id', $id)
            ->where('user_id', $userId)
            ->with(['bankSampah', 'detailSetoran.katalogSampah', 'setoranLog'])
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran sampah tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $setoran
        ]);
    }

    /**
     * Membatalkan setoran sampah.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $userId = Auth::id();
        $setoran = SetoranSampah::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$setoran) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran sampah tidak ditemukan'
            ], 404);
        }

        if ($setoran->status !== 'menunggu_konfirmasi') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya setoran dengan status menunggu konfirmasi yang dapat dibatalkan'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $setoran->status = 'dibatalkan';
            $setoran->save();

            // Buat log status
            $this->createSetoranLog($setoran->id, 'dibatalkan', 'Setoran dibatalkan oleh nasabah');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Setoran sampah berhasil dibatalkan',
                'data' => $setoran
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan setoran sampah: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan history setoran sampah.
     *
     * @return \Illuminate\Http\Response
     */
    public function history(Request $request)
    {
        $userId = Auth::id();
        $query = SetoranSampah::where('user_id', $userId)
            ->with(['bankSampah']);

        // Filter berdasarkan status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default hanya menampilkan yang sudah selesai atau dibatalkan
            $query->whereIn('status', ['selesai', 'dibatalkan']);
        }

        // Filter berdasarkan bank sampah
        if ($request->has('bank_sampah_id')) {
            $query->where('bank_sampah_id', $request->bank_sampah_id);
        }

        // Filter berdasarkan tanggal
        if ($request->has('tanggal_mulai') && $request->has('tanggal_akhir')) {
            $query->whereBetween('tanggal_setoran', [$request->tanggal_mulai, $request->tanggal_akhir]);
        }

        $history = $query->orderBy('tanggal_setoran', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Mendapatkan statistik setoran sampah nasabah.
     *
     * @return \Illuminate\Http\Response
     */
    public function statistics(Request $request)
    {
        $userId = Auth::id();

        // Filter berdasarkan bank sampah
        $bankSampahId = $request->bank_sampah_id;

        // Filter berdasarkan periode
        $periode = $request->periode ?? 'bulan_ini'; // bulan_ini, tahun_ini, semua

        $query = SetoranSampah::where('user_id', $userId)
            ->where('status', 'selesai');

        if ($bankSampahId) {
            $query->where('bank_sampah_id', $bankSampahId);
        }

        // Filter berdasarkan periode
        if ($periode === 'bulan_ini') {
            $query->whereMonth('tanggal_setoran', now()->month)
                ->whereYear('tanggal_setoran', now()->year);
        } elseif ($periode === 'tahun_ini') {
            $query->whereYear('tanggal_setoran', now()->year);
        }

        // Hitung statistik
        $totalSetoran = $query->count();
        $totalBerat = $query->sum('total_berat');
        $totalNilai = $query->sum('total_nilai');

        // Dapatkan jenis sampah yang paling banyak disetor
        $jenisSampahTerbanyak = DetailSetoran::select('katalog_sampah_id', DB::raw('SUM(berat) as total_berat'))
            ->whereIn('setoran_sampah_id', function ($q) use ($userId, $bankSampahId, $periode) {
                $q->select('id')
                    ->from('setoran_sampah')
                    ->where('user_id', $userId)
                    ->where('status', 'selesai');

                if ($bankSampahId) {
                    $q->where('bank_sampah_id', $bankSampahId);
                }

                if ($periode === 'bulan_ini') {
                    $q->whereMonth('tanggal_setoran', now()->month)
                        ->whereYear('tanggal_setoran', now()->year);
                } elseif ($periode === 'tahun_ini') {
                    $q->whereYear('tanggal_setoran', now()->year);
                }
            })
            ->groupBy('katalog_sampah_id')
            ->orderBy('total_berat', 'desc')
            ->with('katalogSampah')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_setoran' => $totalSetoran,
                'total_berat' => $totalBerat,
                'total_nilai' => $totalNilai,
                'jenis_sampah_terbanyak' => $jenisSampahTerbanyak ? [
                    'nama' => $jenisSampahTerbanyak->katalogSampah->nama_sampah,
                    'total_berat' => $jenisSampahTerbanyak->total_berat
                ] : null
            ]
        ]);
    }

    /**
     * Generate kode setoran unik
     */
    private function generateKodeSetoran()
    {
        $prefix = 'ST';
        $date = now()->format('Ymd');
        $randomString = strtoupper(Str::random(4));

        return $prefix . $date . $randomString;
    }

    /**
     * Membuat log status setoran
     */
    private function createSetoranLog($setoranId, $status, $keterangan = null)
    {
        $log = new SetoranSampahLog();
        $log->setoran_sampah_id = $setoranId;
        $log->status = $status;
        $log->keterangan = $keterangan;
        $log->created_by = Auth::id();
        $log->save();

        return $log;
    }

    /**
     * Validasi tanggal setoran dengan jadwal bank sampah
     */
    private function isTanggalSetoranValid($tanggalSetoran, $jadwalBankSampah)
    {
        // Implementasi validasi tanggal setoran sesuai jadwal bank sampah
        // Ini adalah contoh sederhana, mungkin perlu disesuaikan dengan logika bisnis

        // Jika jadwal bank sampah adalah tanggal tertentu setiap bulan (misal tanggal 15)
        if (is_numeric($jadwalBankSampah)) {
            return $tanggalSetoran->day == $jadwalBankSampah;
        }

        // Jika jadwal bank sampah adalah hari tertentu dalam seminggu (misal 'Monday')
        return $tanggalSetoran->format('l') == $jadwalBankSampah;
    }


    /**
     * Mendapatkan katalog sampah berdasarkan bank sampah dan kategori.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getKatalogSampah(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kategori' => 'sometimes|integer|in:0,1', // 0: Kering, 1: Basah
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $query = KatalogSampah::where('bank_sampah_id', $request->bank_sampah_id)
            ->where('status_aktif', true);

        if ($request->has('kategori')) {
            $query->where('kategori_sampah', $request->kategori);
        }

        $katalog = $query->get();

        return response()->json([
            'success' => true,
            'data' => $katalog
        ]);
    }
}
