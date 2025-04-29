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
    // Status constants
    const STATUS_PENGAJUAN = 'Pengajuan';
    const STATUS_DIPROSES = 'Diproses';
    const STATUS_SELESAI = 'Selesai';
    const STATUS_BATAL = 'Dibatalkan';

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
        if ($request->has('status_setoran')) {
            $query->where('status_setoran', $request->status_setoran);
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

        // Format data untuk tampilan di aplikasi
        foreach ($setoran as $item) {
            $item->total_berat_format = number_format($item->total_berat, 2, ',', '.') . ' kg';
            $item->total_nilai_format = 'Rp ' . number_format($item->total_saldo, 0, ',', '.');
            $item->jumlah_item = $item->detailSetoran->count();
        }

        return response()->json([
            'success' => true,
            'data' => $setoran
        ]);
    }

    /**
     * Mendapatkan daftar bank sampah yang terdaftar oleh nasabah.
     *
     * @return \Illuminate\Http\Response
     */
    public function getMemberBankSampah()
    {
        $userId = Auth::id();

        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('status_keanggotaan', 'aktif')
            ->with('bankSampah')
            ->get();

        if ($memberBankSampah->isEmpty()) {
            return response()->json([
                'success' => true,
                'is_member' => false,
                'message' => 'Anda belum terdaftar di bank sampah manapun',
                'data' => []
            ]);
        }

        // Format data bank sampah untuk tampilan
        $bankSampahList = [];
        foreach ($memberBankSampah as $member) {
            $bankSampah = $member->bankSampah;

            // Periksa jam operasional
            $statusOperasional = $bankSampah->status_operasional ? 'Aktif' : 'Tutup';

            $bankSampahList[] = [
                'id' => $bankSampah->id,
                'nama' => $bankSampah->nama_bank_sampah,
                'alamat' => $bankSampah->alamat_bank_sampah,
                'status_operasional' => $statusOperasional,
                'jam_operasional' => $bankSampah->jam_operasional,
                'kode_member' => $member->kode_member,
                'tanggal_bergabung' => $member->created_at->format('d-m-Y')
            ];
        }

        return response()->json([
            'success' => true,
            'is_member' => true,
            'data' => $bankSampahList
        ]);
    }

    /**
     * Membuat pengajuan setoran sampah baru (tanpa berat).
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createPengajuan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'tanggal_setoran' => 'required|date_format:Y-m-d',
            'waktu_setoran' => 'required|date_format:H:i',
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|exists:katalog_sampah,id',
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

        DB::beginTransaction();
        try {
            // Dapatkan bank sampah untuk informasi kode
            $bankSampah = BankSampah::find($bankSampahId);

            // Generate kode setoran
            $kodeSetoran = $this->generateKodeSetoran($bankSampah->kode_bank_sampah);

            // Buat record setoran baru
            $setoran = new SetoranSampah();
            $setoran->kode_setoran_sampah = $kodeSetoran;
            $setoran->user_id = $userId;
            $setoran->bank_sampah_id = $bankSampahId;
            $setoran->tanggal_setoran = $request->tanggal_setoran;
            $setoran->waktu_setoran = $request->waktu_setoran;
            $setoran->status_setoran = self::STATUS_PENGAJUAN;
            $setoran->catatan_status_setoran = $request->catatan;
            $setoran->total_berat = 0; // Belum ada berat pada tahap pengajuan
            $setoran->total_saldo = 0; // Belum ada nilai pada tahap pengajuan
            $setoran->save();

            // Buat log status
            $this->createSetoranLog($setoran->id, self::STATUS_PENGAJUAN, 'Pengajuan setoran dibuat');

            // Proses detail setoran untuk setiap item yang dipilih
            foreach ($request->item_ids as $itemId) {
                $katalogSampah = KatalogSampah::find($itemId);

                if (!$katalogSampah || $katalogSampah->bank_sampah_id != $bankSampahId) {
                    throw new \Exception('Katalog sampah tidak valid atau tidak terdaftar di bank sampah ini');
                }

                $detailSetoran = new DetailSetoran();
                $detailSetoran->setoran_sampah_id = $setoran->id;
                $detailSetoran->item_sampah_id = $itemId;
                $detailSetoran->berat = 0; // Belum ada berat pada tahap pengajuan
                $detailSetoran->saldo = 0; // Belum ada nilai pada tahap pengajuan
                $detailSetoran->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan setoran sampah berhasil dibuat dan menunggu diproses oleh bank sampah',
                'data' => [
                    'id' => $setoran->id,
                    'kode_setoran' => $setoran->kode_setoran_sampah,
                    'status_setoran' => $setoran->status_setoran,
                    'tanggal_setoran' => $setoran->tanggal_setoran,
                    'waktu_setoran' => $setoran->waktu_setoran,
                    'jumlah_item' => count($request->item_ids)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pengajuan setoran sampah: ' . $e->getMessage()
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

        // Format data untuk tampilan di aplikasi
        $setoran->total_berat_format = number_format($setoran->total_berat, 2, ',', '.') . ' kg';
        $setoran->total_nilai_format = 'Rp ' . number_format($setoran->total_saldo, 0, ',', '.');
        $setoran->total_poin = floor($setoran->total_saldo / 1000);

        // Format detail item dan kelompokkan berdasarkan sub kategori
        $detailBySubKategori = [];

        foreach ($setoran->detailSetoran as $detail) {
            $detail->berat_format = number_format($detail->berat, 2, ',', '.') . ' kg';
            $detail->saldo_format = 'Rp ' . number_format($detail->saldo, 0, ',', '.');

            if ($detail->foto) {
                $detail->foto_url = url('storage/' . $detail->foto);
            }

            // Kelompokkan berdasarkan sub kategori
            $subKategoriId = $detail->katalogSampah->sub_kategori_sampah_id ?? 'none';
            $subKategoriName = $detail->katalogSampah->subKategori ?
                $detail->katalogSampah->subKategori->nama_sub_kategori : 'Lainnya';

            if (!isset($detailBySubKategori[$subKategoriId])) {
                $detailBySubKategori[$subKategoriId] = [
                    'id' => $subKategoriId,
                    'nama' => $subKategoriName,
                    'items' => []
                ];
            }

            $detailBySubKategori[$subKategoriId]['items'][] = $detail;
        }

        // Prepare timeline status
        $timeline = [];
        foreach ($setoran->setoranLog as $log) {
            $timeline[] = [
                'status_setoran' => $log->status_setoran,
                'tanggal' => $log->created_at->format('d M Y'),
                'waktu' => $log->created_at->format('H:i'),
                'keterangan' => $log->catatan
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'setoran' => $setoran,
                'item_by_sub_kategori' => array_values($detailBySubKategori),
                'timeline' => $timeline,
                'is_editable' => $setoran->status_setoran === self::STATUS_PENGAJUAN,
                'is_cancelable' => $this->isSetoranCancelable($setoran)
            ]
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

        if ($setoran->status_setoran !== self::STATUS_PENGAJUAN) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya setoran dengan status pengajuan yang dapat dibatalkan'
            ], 422);
        }

        // Validasi batas waktu pembatalan (hanya boleh dibatalkan dalam 24 jam setelah pengajuan)
        if (!$this->isSetoranCancelable($setoran)) {
            return response()->json([
                'success' => false,
                'message' => 'Setoran tidak dapat dibatalkan karena sudah lebih dari 24 jam sejak pengajuan'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $setoran->status_setoran = self::STATUS_BATAL;
            $setoran->save();

            // Buat log status
            $this->createSetoranLog($setoran->id, self::STATUS_BATAL, 'Setoran dibatalkan oleh nasabah');

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
     * Mendapatkan list setoran "berlangsung" (pengajuan dan diproses).
     *
     * @return \Illuminate\Http\Response
     */
    public function ongoing()
    {
        $userId = Auth::id();
        $setoran = SetoranSampah::where('user_id', $userId)
            ->whereIn('status_setoran', [self::STATUS_PENGAJUAN, self::STATUS_DIPROSES])
            ->with(['bankSampah', 'detailSetoran'])
            ->orderBy('tanggal_setoran', 'desc')
            ->paginate(10);

        // Format data untuk tampilan di aplikasi
        foreach ($setoran as $item) {
            $item->total_berat_format = number_format($item->total_berat, 2, ',', '.') . ' kg';
            $item->total_nilai_format = 'Rp ' . number_format($item->total_saldo, 0, ',', '.');
            $item->jumlah_item = $item->detailSetoran->count();
            $item->is_cancelable = $this->isSetoranCancelable($item);
        }

        return response()->json([
            'success' => true,
            'data' => $setoran
        ]);
    }

    /**
     * Mendapatkan list riwayat setoran (selesai dan batal).
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function history(Request $request)
    {
        $userId = Auth::id();
        $query = SetoranSampah::where('user_id', $userId)
            ->with(['bankSampah']);

        // Filter berdasarkan status
        if ($request->has('status_setoran') && in_array($request->status_setoran, [self::STATUS_SELESAI, self::STATUS_BATAL])) {
            $query->where('status_setoran', $request->status_setoran);
        } else {
            // Default menampilkan yang sudah selesai atau dibatalkan
            $query->whereIn('status_setoran', [self::STATUS_SELESAI, self::STATUS_BATAL]);
        }

        // Filter berdasarkan bank sampah
        if ($request->has('bank_sampah_id')) {
            $query->where('bank_sampah_id', $request->bank_sampah_id);
        }

        $history = $query->orderBy('tanggal_setoran', 'desc')
            ->with('detailSetoran')
            ->paginate(10);

        // Format data untuk tampilan di aplikasi
        foreach ($history as $item) {
            $item->total_berat_format = number_format($item->total_berat, 2, ',', '.') . ' kg';
            $item->total_nilai_format = 'Rp ' . number_format($item->total_saldo, 0, ',', '.');
            $item->jumlah_item = $item->detailSetoran->count();
        }

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Mendapatkan timeline status setoran.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function getStatusTimeline($id)
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

        // Ambil log status setoran
        $logs = SetoranSampahLog::where('setoran_sampah_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Format timeline yang mudah dibaca
        $timeline = [];
        foreach ($logs as $log) {
            $timeline[] = [
                'status_setoran' => $log->status_setoran,
                'tanggal' => $log->created_at->format('d M Y'),
                'waktu' => $log->created_at->format('H:i'),
                'keterangan' => $log->catatan,
                'tanggal_lengkap' => $log->created_at->format('Y-m-d H:i:s')
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kode_setoran' => $setoran->kode_setoran_sampah,
                'status_setoran_saat_ini' => $setoran->status_setoran,
                'timeline' => $timeline
            ]
        ]);
    }

    /**
     * Mendapatkan statistik ringkasan setoran sampah.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDashboardStats()
    {
        $userId = Auth::id();

        // Hitung jumlah setoran per status
        $pengajuanCount = SetoranSampah::where('user_id', $userId)
            ->where('status_setoran', self::STATUS_PENGAJUAN)
            ->count();

        $diprosesCount = SetoranSampah::where('user_id', $userId)
            ->where('status_setoran', self::STATUS_DIPROSES)
            ->count();

        $selesaiCount = SetoranSampah::where('user_id', $userId)
            ->where('status_setoran', self::STATUS_SELESAI)
            ->count();

        $batalCount = SetoranSampah::where('user_id', $userId)
            ->where('status_setoran', self::STATUS_BATAL)
            ->count();

        // Hitung total berat dan nilai dari setoran yang selesai
        $totalStats = SetoranSampah::where('user_id', $userId)
            ->where('status_setoran', self::STATUS_SELESAI)
            ->selectRaw('SUM(total_berat) as total_berat, SUM(total_saldo) as total_saldo')
            ->first();

        // Dapatkan setoran terakhir
        $lastSetoran = SetoranSampah::where('user_id', $userId)
            ->with('bankSampah')
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'jumlah_setoran' => [
                    'pengajuan' => $pengajuanCount,
                    'diproses' => $diprosesCount,
                    'selesai' => $selesaiCount,
                    'batal' => $batalCount,
                    'total' => $pengajuanCount + $diprosesCount + $selesaiCount + $batalCount
                ],
                'total_statistik' => [
                    'total_berat' => $totalStats ? $totalStats->total_berat : 0,
                    'total_berat_format' => $totalStats ? number_format($totalStats->total_berat, 2, ',', '.') . ' kg' : '0 kg',
                    'total_saldo' => $totalStats ? $totalStats->total_saldo : 0,
                    'total_saldo_format' => $totalStats ? 'Rp ' . number_format($totalStats->total_saldo, 0, ',', '.') : 'Rp 0',
                    'perkiraan_poin' => $totalStats ? floor($totalStats->total_saldo / 1000) : 0
                ],
                'setoran_terakhir' => $lastSetoran ? [
                    'id' => $lastSetoran->id,
                    'kode_setoran' => $lastSetoran->kode_setoran_sampah,
                    'status_setoran' => $lastSetoran->status_setoran,
                    'bank_sampah' => $lastSetoran->bankSampah ? $lastSetoran->bankSampah->nama_bank_sampah : null,
                    'tanggal' => $lastSetoran->created_at->format('d M Y'),
                    'total_nilai_format' => 'Rp ' . number_format($lastSetoran->total_saldo, 0, ',', '.')
                ] : null
            ]
        ]);
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
            'kategori_sampah' => 'sometimes|integer|in:0,1', // 0: Kering, 1: Basah
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $bankSampahId = $request->bank_sampah_id;

        // Dapatkan informasi bank sampah
        $bankSampah = BankSampah::find($bankSampahId);
        if (!$bankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Bank sampah tidak ditemukan'
            ], 404);
        }

        // Query katalog sampah
        $query = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan kategori jika ada
        if ($request->has('kategori_sampah')) {
            $query->where('kategori_sampah', $request->kategori_sampah);
        }

        $katalogSampah = $query->get();

        // Hitung jumlah item per kategori
        $jumlahKering = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->where('kategori_sampah', 0)
            ->count();

        $jumlahBasah = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->where('kategori_sampah', 1)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama' => $bankSampah->nama_bank_sampah,
                    'alamat' => $bankSampah->alamat_bank_sampah,
                    'tanggal_setoran' => $bankSampah->tanggal_setoran,
                    'status_operasional' => $bankSampah->status_operasional ? 'Aktif' : 'Tutup',
                    'nomor_telepon' => $bankSampah->nomor_telepon_publik
                ],
                'katalog_sampah' => $katalogSampah,
                'info_kategori' => [
                    'jumlah_kering' => $jumlahKering,
                    'jumlah_basah' => $jumlahBasah
                ]
            ]
        ]);
    }

    /**
     * Generate kode setoran unik
     * Format: [KODE_BANK_SAMPAH(3)][HURUF_ACAK(1)][ANGKA_URUT(6)]
     */
    private function generateKodeSetoran($kodeBankSampah)
    {
        // Ambil 3 huruf pertama kode bank sampah
        $prefix = substr($kodeBankSampah, 0, 3);

        // Generate 1 huruf acak A-Z
        $randomLetter = chr(rand(65, 90)); // ASCII code for A-Z

        // Dapatkan angka urut terakhir
        $lastCode = SetoranSampah::where('kode_setoran_sampah', 'like', $prefix . $randomLetter . '%')
            ->orderBy('id', 'desc')
            ->value('kode_setoran_sampah');

        $nextNum = 1;
        if ($lastCode) {
            // Extract angka dari kode terakhir
            $lastNum = (int)substr($lastCode, 4);
            $nextNum = $lastNum + 1;
        }

        // Format angka dengan 6 digit
        $numStr = str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        return $prefix . $randomLetter . $numStr;
    }

    /**
     * Membuat log status setoran
     */
    private function createSetoranLog($setoranId, $status, $keterangan = null)
    {
        $log = new SetoranSampahLog();
        $log->setoran_sampah_id = $setoranId;
        $log->status_setoran = $status;
        $log->catatan = $keterangan;
        $log->created_by = Auth::id();
        $log->save();

        return $log;
    }

    /**
     * Cek apakah setoran masih bisa dibatalkan
     * (hanya bisa dibatalkan dalam 24 jam setelah pengajuan dan status masih pengajuan)
     */
    private function isSetoranCancelable($setoran)
    {
        if ($setoran->status_setoran !== self::STATUS_PENGAJUAN) {
            return false;
        }

        $createdTime = Carbon::parse($setoran->created_at);
        $now = Carbon::now();
        $hoursSinceCreation = $createdTime->diffInHours($now);

        return $hoursSinceCreation <= 24;
    }
}