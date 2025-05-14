<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanSampahController extends Controller
{
    /**
     * Mengecek apakah user memiliki akses ke bank sampah
     *
     * @param int $bankSampahId
     * @return bool
     */
    private function checkUserAccessToBankSampah($bankSampahId)
    {
        $user = Auth::user();

        // Cek apakah user berperan sebagai nasabah
        if ($user->role !== 'nasabah') {
            return false;
        }

        // Cek apakah user terdaftar sebagai nasabah di bank sampah ini
        $memberCheck = DB::table('member_bank_sampah')
            ->where('user_id', $user->id)
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_keanggotaan', 'aktif')
            ->exists();

        return $memberCheck;
    }

    /**
     * Mendapatkan daftar bank sampah yang terdaftar oleh user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankSampahList()
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Ambil daftar bank sampah yang terdaftar
        $bankSampahList = DB::table('member_bank_sampah')
            ->join('bank_sampah', 'member_bank_sampah.bank_sampah_id', '=', 'bank_sampah.id')
            ->where('member_bank_sampah.user_id', $user->id)
            ->where('member_bank_sampah.status_keanggotaan', 'aktif')
            ->select(
                'bank_sampah.id',
                'bank_sampah.nama_bank_sampah',
                'bank_sampah.alamat_bank_sampah',
                'member_bank_sampah.kode_nasabah',
                'member_bank_sampah.saldo'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bankSampahList
        ]);
    }

    /**
     * Mendapatkan data ringkasan laporan sampah
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLaporanSampahSummary(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id'
        ]);

        $bankSampahId = $request->bank_sampah_id;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Ambil data total tonase sampah
        $totalTonase = DB::table('setoran_sampah')
            ->where('user_id', $user->id)
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_setoran', 'Selesai')
            ->sum('total_berat');

        // Ambil data total item sampah
        $totalItem = DB::table('setoran_sampah')
            ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
            ->where('setoran_sampah.user_id', $user->id)
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai')
            ->count('detail_setoran.id');

        // Ambil data total penjualan
        $totalPenjualan = DB::table('setoran_sampah')
            ->where('user_id', $user->id)
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_setoran', 'Selesai')
            ->sum('total_saldo');

        // Ambil data total transaksi
        $totalTransaksi = DB::table('setoran_sampah')
            ->where('user_id', $user->id)
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_setoran', 'Selesai')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'tonase' => [
                    'total_berat' => $totalTonase,
                    'total_item' => $totalItem,
                ],
                'penjualan' => [
                    'total_saldo' => $totalPenjualan,
                    'total_transaksi' => $totalTransaksi,
                ]
            ]
        ]);
    }

    /**
     * Mendapatkan data tonase sampah per kategori
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTonaseSampahPerKategori(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'jenis_sampah' => 'nullable|in:kering,basah'
        ]);

        $bankSampahId = $request->bank_sampah_id;
        $jenisSampah = $request->jenis_sampah;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Query untuk data tonase per sub kategori
        $query = DB::table('setoran_sampah')
            ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
            ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
            ->leftJoin('sub_kategori_sampah', 'katalog_sampah.sub_kategori_sampah_id', '=', 'sub_kategori_sampah.id')
            ->where('setoran_sampah.user_id', $user->id)
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai');

        // Filter berdasarkan jenis sampah jika dipilih
        if ($jenisSampah) {
            if ($jenisSampah === 'kering') {
                $query->where('katalog_sampah.kategori_sampah', 1);
            } else {
                $query->where('katalog_sampah.kategori_sampah', 2);
            }
        }

        $tonasePerKategori = $query
            ->select(
                DB::raw('COALESCE(sub_kategori_sampah.nama_sub_kategori, katalog_sampah.nama_item_sampah) as kategori'),
                DB::raw('SUM(detail_setoran.berat) as total_berat')
            )
            ->groupBy('kategori')
            ->orderBy('total_berat', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tonasePerKategori
        ]);
    }

    /**
     * Mendapatkan data penjualan sampah per kategori
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPenjualanSampahPerKategori(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'jenis_sampah' => 'nullable|in:kering,basah'
        ]);

        $bankSampahId = $request->bank_sampah_id;
        $jenisSampah = $request->jenis_sampah;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Ambil data penjualan per kategori sampah
        $query = DB::table('setoran_sampah')
            ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
            ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
            ->where('setoran_sampah.user_id', $user->id)
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai');

        // Filter berdasarkan jenis sampah jika dipilih
        if ($jenisSampah) {
            if ($jenisSampah === 'kering') {
                $query->where('katalog_sampah.kategori_sampah', 1);
            } else {
                $query->where('katalog_sampah.kategori_sampah', 2);
            }
        }

        $penjualanPerKategori = $query
            ->select(
                DB::raw('CASE WHEN katalog_sampah.kategori_sampah = 1 THEN "Sampah Kering" ELSE "Sampah Basah" END as kategori'),
                DB::raw('SUM(detail_setoran.saldo) as total_saldo')
            )
            ->groupBy('kategori')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $penjualanPerKategori
        ]);
    }

    /**
     * Mendapatkan data tren tonase sampah
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTrenTonaseSampah(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'jenis_sampah' => 'nullable|in:kering,basah',
            'periode' => 'required|in:mingguan,bulanan,tahunan'
        ]);

        $bankSampahId = $request->bank_sampah_id;
        $jenisSampah = $request->jenis_sampah;
        $periode = $request->periode;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Tentukan format tanggal dan grouping berdasarkan periode
        $dateFormat = 'Y-m-d';
        $groupBy = 'date';

        if ($periode === 'mingguan') {
            $dateFormat = 'Y-m-d'; // Format harian
            $groupBy = 'date';
            $limit = 7; // 7 hari terakhir
        } elseif ($periode === 'bulanan') {
            $dateFormat = 'Y-m-W'; // Format mingguan (tahun-bulan-minggu)
            $groupBy = 'week';
            $limit = 4; // 4 minggu terakhir
        } elseif ($periode === 'tahunan') {
            $dateFormat = 'Y-m'; // Format bulanan
            $groupBy = 'month';
            $limit = 12; // 12 bulan terakhir
        }

        // Query dasar
        $query = DB::table('setoran_sampah')
            ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
            ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
            ->where('setoran_sampah.user_id', $user->id)
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai');

        // Filter berdasarkan jenis sampah jika dipilih
        if ($jenisSampah) {
            if ($jenisSampah === 'kering') {
                $query->where('katalog_sampah.kategori_sampah', 1);
            } else {
                $query->where('katalog_sampah.kategori_sampah', 2);
            }
        }

        // Tentukan awal periode berdasarkan periode yang dipilih
        if ($periode === 'mingguan') {
            $startDate = Carbon::now()->subDays(6);
        } elseif ($periode === 'bulanan') {
            $startDate = Carbon::now()->subWeeks(3);
        } else {
            $startDate = Carbon::now()->subMonths(11);
        }

        $query->where('setoran_sampah.tanggal_setoran', '>=', $startDate->format('Y-m-d'));

        // Group by berdasarkan periode
        if ($groupBy === 'date') {
            $trenData = $query
                ->select(
                    DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m-%d') as periode"),
                    DB::raw('SUM(detail_setoran.berat) as total_berat')
                )
                ->groupBy('periode')
                ->orderBy('periode')
                ->get();
        } elseif ($groupBy === 'week') {
            $trenData = $query
                ->select(
                    DB::raw("CONCAT(YEAR(setoran_sampah.tanggal_setoran), '-', WEEK(setoran_sampah.tanggal_setoran)) as periode"),
                    DB::raw('SUM(detail_setoran.berat) as total_berat')
                )
                ->groupBy('periode')
                ->orderBy('periode')
                ->get();
        } else {
            $trenData = $query
                ->select(
                    DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m') as periode"),
                    DB::raw('SUM(detail_setoran.berat) as total_berat')
                )
                ->groupBy('periode')
                ->orderBy('periode')
                ->get();
        }

        // Format label periode yang lebih user-friendly
        $formattedTrenData = $trenData->map(function($item) use ($periode, $groupBy) {
            if ($groupBy === 'date') {
                $date = Carbon::parse($item->periode);
                $item->label = $date->format('D'); // Format hari (Sen, Sel, dll)
            } elseif ($groupBy === 'week') {
                // Extract year and week number
                list($year, $week) = explode('-', $item->periode);
                $item->label = "Minggu-{$week}";
            } else {
                $date = Carbon::parse($item->periode . '-01');
                $item->label = $date->format('M'); // Format bulan (Jan, Feb, dll)
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'periode' => $periode,
            'data' => $formattedTrenData
        ]);
    }

    /**
     * Mendapatkan data tren penjualan sampah
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTrenPenjualanSampah(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'jenis_sampah' => 'nullable|in:kering,basah',
            'periode' => 'required|in:mingguan,bulanan,tahunan'
        ]);

        $bankSampahId = $request->bank_sampah_id;
        $jenisSampah = $request->jenis_sampah;
        $periode = $request->periode;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Tentukan format tanggal dan grouping berdasarkan periode
        $dateFormat = 'Y-m-d';
        $groupBy = 'date';

        if ($periode === 'mingguan') {
            $dateFormat = 'Y-m-d'; // Format harian
            $groupBy = 'date';
            $limit = 7; // 7 hari terakhir
        } elseif ($periode === 'bulanan') {
            $dateFormat = 'Y-m-W'; // Format mingguan (tahun-bulan-minggu)
            $groupBy = 'week';
            $limit = 4; // 4 minggu terakhir
        } elseif ($periode === 'tahunan') {
            $dateFormat = 'Y-m'; // Format bulanan
            $groupBy = 'month';
            $limit = 12; // 12 bulan terakhir
        }

        // Query dasar
        $query = DB::table('setoran_sampah')
            ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
            ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
            ->where('setoran_sampah.user_id', $user->id)
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai');

        // Filter berdasarkan jenis sampah jika dipilih
        if ($jenisSampah) {
            if ($jenisSampah === 'kering') {
                $query->where('katalog_sampah.kategori_sampah', 1);
            } else {
                $query->where('katalog_sampah.kategori_sampah', 2);
            }
        }

        // Tentukan awal periode berdasarkan periode yang dipilih
        if ($periode === 'mingguan') {
            $startDate = Carbon::now()->subDays(6);
        } elseif ($periode === 'bulanan') {
            $startDate = Carbon::now()->subWeeks(3);
        } else {
            $startDate = Carbon::now()->subMonths(11);
        }

        $query->where('setoran_sampah.tanggal_setoran', '>=', $startDate->format('Y-m-d'));

        // Group by berdasarkan periode
        if ($groupBy === 'date') {
            $trenData = $query
                ->select(
                    DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m-%d') as periode"),
                    DB::raw('SUM(detail_setoran.saldo) as total_saldo')
                )
                ->groupBy('periode')
                ->orderBy('periode')
                ->get();
        } elseif ($groupBy === 'week') {
            $trenData = $query
                ->select(
                    DB::raw("CONCAT(YEAR(setoran_sampah.tanggal_setoran), '-', WEEK(setoran_sampah.tanggal_setoran)) as periode"),
                    DB::raw('SUM(detail_setoran.saldo) as total_saldo')
                )
                ->groupBy('periode')
                ->orderBy('periode')
                ->get();
        } else {
            $trenData = $query
                ->select(
                    DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m') as periode"),
                    DB::raw('SUM(detail_setoran.saldo) as total_saldo')
                )
                ->groupBy('periode')
                ->orderBy('periode')
                ->get();
        }

        // Format label periode yang lebih user-friendly
        $formattedTrenData = $trenData->map(function($item) use ($periode, $groupBy) {
            if ($groupBy === 'date') {
                $date = Carbon::parse($item->periode);
                $item->label = $date->format('D'); // Format hari (Sen, Sel, dll)
            } elseif ($groupBy === 'week') {
                // Extract year and week number
                list($year, $week) = explode('-', $item->periode);
                $item->label = "Minggu-{$week}";
            } else {
                $date = Carbon::parse($item->periode . '-01');
                $item->label = $date->format('M'); // Format bulan (Jan, Feb, dll)
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'periode' => $periode,
            'data' => $formattedTrenData
        ]);
    }

    /**
     * Mengecek apakah user terdaftar sebagai nasabah untuk akses laporan
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkNasabahStatus()
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Cek apakah user terdaftar sebagai nasabah di bank sampah manapun
        $hasRegistered = DB::table('member_bank_sampah')
            ->where('user_id', $user->id)
            ->where('status_keanggotaan', 'aktif')
            ->exists();

        return response()->json([
            'success' => true,
            'is_registered' => $hasRegistered
        ]);
    }

    /**
     * Mendapatkan riwayat setoran sampah (tonase)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRiwayatTonaseSampah(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'per_page' => 'nullable|integer|min:5|max:50'
        ]);

        $bankSampahId = $request->bank_sampah_id;
        $perPage = $request->per_page ?? 10;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Ambil data riwayat setoran sampah
        $riwayatSetoran = DB::table('setoran_sampah')
            ->where('user_id', $user->id)
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_setoran', 'Selesai')
            ->select(
                'id',
                'kode_setoran_sampah',
                'tanggal_setoran',
                'total_berat',
                DB::raw('(SELECT COUNT(*) FROM detail_setoran WHERE setoran_sampah_id = setoran_sampah.id) as total_item')
            )
            ->orderBy('tanggal_setoran', 'desc')
            ->paginate($perPage);

        // Format data riwayat untuk tampilan terkelompok per tanggal
        $formattedRiwayat = [];
        foreach ($riwayatSetoran as $setoran) {
            $tanggal = Carbon::parse($setoran->tanggal_setoran)->format('Y-m-d');
            $tanggalFormatted = Carbon::parse($setoran->tanggal_setoran)->translatedFormat('l, j F Y');

            if (!isset($formattedRiwayat[$tanggal])) {
                $formattedRiwayat[$tanggal] = [
                    'tanggal' => $tanggalFormatted,
                    'data' => []
                ];
            }

            $formattedRiwayat[$tanggal]['data'][] = [
                'id' => $setoran->id,
                'kode_setoran_sampah' => $setoran->kode_setoran_sampah,
                'total_item' => $setoran->total_item,
                'total_berat' => $setoran->total_berat,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => array_values($formattedRiwayat),
            'pagination' => [
                'current_page' => $riwayatSetoran->currentPage(),
                'last_page' => $riwayatSetoran->lastPage(),
                'per_page' => $riwayatSetoran->perPage(),
                'total' => $riwayatSetoran->total()
            ]
        ]);
    }

    /**
     * Mendapatkan riwayat setoran sampah (penjualan)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRiwayatPenjualanSampah(Request $request)
    {
        $user = Auth::user();

        // Cek role user
        if ($user->role !== 'nasabah') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validasi request
        $request->validate([
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'per_page' => 'nullable|integer|min:5|max:50'
        ]);

        $bankSampahId = $request->bank_sampah_id;
        $perPage = $request->per_page ?? 10;

        // Cek apakah user terdaftar di bank sampah ini
        if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
            ], 403);
        }

        // Ambil data riwayat penjualan sampah
        $riwayatPenjualan = DB::table('setoran_sampah')
        ->where('user_id', $user->id)
        ->where('bank_sampah_id', $bankSampahId)
        ->where('status_setoran', 'Selesai')
        ->select(
            'id',
            'kode_setoran_sampah',
            'tanggal_setoran',
            'total_saldo',
            DB::raw('(SELECT COUNT(*) FROM detail_setoran WHERE setoran_sampah_id = setoran_sampah.id) as total_item')
        )
        ->orderBy('tanggal_setoran', 'desc')
        ->paginate($perPage);

        // Format data riwayat untuk tampilan terkelompok per tanggal
        $formattedRiwayat = [];
        foreach ($riwayatPenjualan as $setoran) {
        $tanggal = Carbon::parse($setoran->tanggal_setoran)->format('Y-m-d');
        $tanggalFormatted = Carbon::parse($setoran->tanggal_setoran)->translatedFormat('l, j F Y');

        if (!isset($formattedRiwayat[$tanggal])) {
            $formattedRiwayat[$tanggal] = [
                'tanggal' => $tanggalFormatted,
                'data' => []
            ];
        }

        $formattedRiwayat[$tanggal]['data'][] = [
            'id' => $setoran->id,
            'kode_setoran_sampah' => $setoran->kode_setoran_sampah,
            'total_item' => $setoran->total_item,
            'total_saldo' => $setoran->total_saldo,
        ];
        }

        return response()->json([
        'success' => true,
        'data' => array_values($formattedRiwayat),
        'pagination' => [
            'current_page' => $riwayatPenjualan->currentPage(),
            'last_page' => $riwayatPenjualan->lastPage(),
            'per_page' => $riwayatPenjualan->perPage(),
            'total' => $riwayatPenjualan->total()
        ]
        ]);
    }


    /**
 * Mendapatkan dashboard data
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getDashboard(Request $request)
{
    $user = Auth::user();

    // Cek role user
    if ($user->role !== 'nasabah') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized access'
        ], 403);
    }

    // Validasi request
    $request->validate([
        'bank_sampah_id' => 'required|exists:bank_sampah,id'
    ]);

    $bankSampahId = $request->bank_sampah_id;

    // Cek apakah user terdaftar di bank sampah ini
    if (!$this->checkUserAccessToBankSampah($bankSampahId)) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak terdaftar sebagai nasabah di bank sampah ini'
        ], 403);
    }

    // Ambil semua data yang diperlukan untuk dashboard
    $summary = $this->getLaporanSampahSummaryData($user->id, $bankSampahId);
    $tonasePerKategori = $this->getTonaseSampahPerKategoriData($user->id, $bankSampahId);
    $penjualanPerKategori = $this->getPenjualanSampahPerKategoriData($user->id, $bankSampahId);
    $trenTonase = $this->getTrenTonaseSampahData($user->id, $bankSampahId, 'mingguan');
    $trenPenjualan = $this->getTrenPenjualanSampahData($user->id, $bankSampahId, 'mingguan');

    return response()->json([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'tonase_per_kategori' => $tonasePerKategori,
            'penjualan_per_kategori' => $penjualanPerKategori,
            'tren_tonase' => [
                'periode' => 'mingguan',
                'data' => $trenTonase
            ],
            'tren_penjualan' => [
                'periode' => 'mingguan',
                'data' => $trenPenjualan
            ]
        ]
    ]);
}

/**
 * Mendapatkan detail item sampah
 *
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function getDetailItem($id)
{
    $user = Auth::user();

    // Cek role user
    if ($user->role !== 'nasabah') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized access'
        ], 403);
    }

    // Ambil data detail setoran
    $detailSetoran = DB::table('setoran_sampah')
        ->where('setoran_sampah.id', $id)
        ->where('setoran_sampah.user_id', $user->id)
        ->where('setoran_sampah.status_setoran', 'Selesai')
        ->select(
            'setoran_sampah.id',
            'setoran_sampah.kode_setoran_sampah',
            'setoran_sampah.tanggal_setoran',
            'setoran_sampah.total_berat',
            'setoran_sampah.total_saldo'
        )
        ->first();

    if (!$detailSetoran) {
        return response()->json([
            'success' => false,
            'message' => 'Detail setoran tidak ditemukan'
        ], 404);
    }

    // Ambil item-item dalam setoran
    $detailItems = DB::table('detail_setoran')
        ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
        ->where('detail_setoran.setoran_sampah_id', $id)
        ->select(
            'detail_setoran.id',
            'katalog_sampah.nama_item_sampah as item_sampah',
            'detail_setoran.berat',
            'katalog_sampah.harga_per_kg',
            'detail_setoran.saldo'
        )
        ->get();

    $detailSetoran->detail_setoran = $detailItems;

    return response()->json([
        'success' => true,
        'data' => $detailSetoran
    ]);
}

/**
 * Mendapatkan kode warna untuk kategori sampah
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function getKodeWarnaKategori()
{
    $user = Auth::user();

    // Cek role user
    if ($user->role !== 'nasabah') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized access'
        ], 403);
    }

    // Data kode warna statis untuk kategori sampah
    $kodeWarna = [
        'kering' => [
            'Kertas' => '#FFA726',
            'Plastik' => '#42A5F5',
            'Logam' => '#7E57C2',
            'Kaca' => '#66BB6A',
            'Kardus' => '#D4AC0D'
        ],
        'basah' => [
            'Sampah Organik' => '#8BC34A',
            'Makanan' => '#FF7043',
            'Tanaman' => '#26A69A',
            'Kompos' => '#8D6E63'
        ],
        'kategori_utama' => [
            'Sampah Kering' => '#2196F3',
            'Sampah Basah' => '#4CAF50'
        ]
    ];

    return response()->json([
        'success' => true,
        'data' => $kodeWarna
    ]);
}

// Helper methods untuk getDashboard (private)
private function getLaporanSampahSummaryData($userId, $bankSampahId)
{
    // Ambil data total tonase sampah
    $totalTonase = DB::table('setoran_sampah')
        ->where('user_id', $userId)
        ->where('bank_sampah_id', $bankSampahId)
        ->where('status_setoran', 'Selesai')
        ->sum('total_berat');

    // Ambil data total item sampah
    $totalItem = DB::table('setoran_sampah')
        ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
        ->where('setoran_sampah.user_id', $userId)
        ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
        ->where('setoran_sampah.status_setoran', 'Selesai')
        ->count('detail_setoran.id');

    // Ambil data total penjualan
    $totalPenjualan = DB::table('setoran_sampah')
        ->where('user_id', $userId)
        ->where('bank_sampah_id', $bankSampahId)
        ->where('status_setoran', 'Selesai')
        ->sum('total_saldo');

    // Ambil data total transaksi
    $totalTransaksi = DB::table('setoran_sampah')
        ->where('user_id', $userId)
        ->where('bank_sampah_id', $bankSampahId)
        ->where('status_setoran', 'Selesai')
        ->count();

    return [
        'tonase' => [
            'total_berat' => $totalTonase,
            'total_item' => $totalItem,
        ],
        'penjualan' => [
            'total_saldo' => $totalPenjualan,
            'total_transaksi' => $totalTransaksi,
        ]
    ];
}

private function getTonaseSampahPerKategoriData($userId, $bankSampahId, $jenisSampah = null)
{
    // Query untuk data tonase per sub kategori
    $query = DB::table('setoran_sampah')
        ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
        ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
        ->leftJoin('sub_kategori_sampah', 'katalog_sampah.sub_kategori_sampah_id', '=', 'sub_kategori_sampah.id')
        ->where('setoran_sampah.user_id', $userId)
        ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
        ->where('setoran_sampah.status_setoran', 'Selesai');

    // Filter berdasarkan jenis sampah jika dipilih
    if ($jenisSampah) {
        if ($jenisSampah === 'kering') {
            $query->where('katalog_sampah.kategori_sampah', 1);
        } else {
            $query->where('katalog_sampah.kategori_sampah', 2);
        }
    }

    return $query
        ->select(
            DB::raw('COALESCE(sub_kategori_sampah.nama_sub_kategori, katalog_sampah.nama_item_sampah) as kategori'),
            DB::raw('SUM(detail_setoran.berat) as total_berat')
        )
        ->groupBy('kategori')
        ->orderBy('total_berat', 'desc')
        ->limit(5)
        ->get();
}

private function getPenjualanSampahPerKategoriData($userId, $bankSampahId, $jenisSampah = null)
{
    // Ambil data penjualan per kategori sampah
    $query = DB::table('setoran_sampah')
        ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
        ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
        ->where('setoran_sampah.user_id', $userId)
        ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
        ->where('setoran_sampah.status_setoran', 'Selesai');

    // Filter berdasarkan jenis sampah jika dipilih
    if ($jenisSampah) {
        if ($jenisSampah === 'kering') {
            $query->where('katalog_sampah.kategori_sampah', 1);
        } else {
            $query->where('katalog_sampah.kategori_sampah', 2);
        }
    }

    return $query
        ->select(
            DB::raw('CASE WHEN katalog_sampah.kategori_sampah = 1 THEN "Sampah Kering" ELSE "Sampah Basah" END as kategori'),
            DB::raw('SUM(detail_setoran.saldo) as total_saldo')
        )
        ->groupBy('kategori')
        ->get();
}

private function getTrenTonaseSampahData($userId, $bankSampahId, $periode = 'mingguan', $jenisSampah = null)
{
    // Tentukan format tanggal dan grouping berdasarkan periode
    $dateFormat = 'Y-m-d';
    $groupBy = 'date';
    $startDate = null;

    if ($periode === 'mingguan') {
        $dateFormat = 'Y-m-d'; // Format harian
        $groupBy = 'date';
        $startDate = Carbon::now()->subDays(6);
    } elseif ($periode === 'bulanan') {
        $dateFormat = 'Y-m-W'; // Format mingguan (tahun-bulan-minggu)
        $groupBy = 'week';
        $startDate = Carbon::now()->subWeeks(3);
    } else {
        $dateFormat = 'Y-m'; // Format bulanan
        $groupBy = 'month';
        $startDate = Carbon::now()->subMonths(11);
    }

    // Query dasar
    $query = DB::table('setoran_sampah')
        ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
        ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
        ->where('setoran_sampah.user_id', $userId)
        ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
        ->where('setoran_sampah.status_setoran', 'Selesai')
        ->where('setoran_sampah.tanggal_setoran', '>=', $startDate->format('Y-m-d'));

    // Filter berdasarkan jenis sampah jika dipilih
    if ($jenisSampah) {
        if ($jenisSampah === 'kering') {
            $query->where('katalog_sampah.kategori_sampah', 1);
        } else {
            $query->where('katalog_sampah.kategori_sampah', 2);
        }
    }

    // Group by berdasarkan periode
    if ($groupBy === 'date') {
        $trenData = $query
            ->select(
                DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m-%d') as periode"),
                DB::raw('SUM(detail_setoran.berat) as total_berat')
            )
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    } elseif ($groupBy === 'week') {
        $trenData = $query
            ->select(
                DB::raw("CONCAT(YEAR(setoran_sampah.tanggal_setoran), '-', WEEK(setoran_sampah.tanggal_setoran)) as periode"),
                DB::raw('SUM(detail_setoran.berat) as total_berat')
            )
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    } else {
        $trenData = $query
            ->select(
                DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m') as periode"),
                DB::raw('SUM(detail_setoran.berat) as total_berat')
            )
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    }

    // Format label periode yang lebih user-friendly
    return $trenData->map(function($item) use ($periode, $groupBy) {
        if ($groupBy === 'date') {
            $date = Carbon::parse($item->periode);
            $item->label = $date->format('D'); // Format hari (Sen, Sel, dll)
        } elseif ($groupBy === 'week') {
            // Extract year and week number
            list($year, $week) = explode('-', $item->periode);
            $item->label = "Minggu-{$week}";
        } else {
            $date = Carbon::parse($item->periode . '-01');
            $item->label = $date->format('M'); // Format bulan (Jan, Feb, dll)
        }
        return $item;
    });
}

private function getTrenPenjualanSampahData($userId, $bankSampahId, $periode = 'mingguan', $jenisSampah = null)
{
    // Tentukan format tanggal dan grouping berdasarkan periode
    $dateFormat = 'Y-m-d';
    $groupBy = 'date';
    $startDate = null;

    if ($periode === 'mingguan') {
        $dateFormat = 'Y-m-d'; // Format harian
        $groupBy = 'date';
        $startDate = Carbon::now()->subDays(6);
    } elseif ($periode === 'bulanan') {
        $dateFormat = 'Y-m-W'; // Format mingguan (tahun-bulan-minggu)
        $groupBy = 'week';
        $startDate = Carbon::now()->subWeeks(3);
    } else {
        $dateFormat = 'Y-m'; // Format bulanan
        $groupBy = 'month';
        $startDate = Carbon::now()->subMonths(11);
    }

    // Query dasar
    $query = DB::table('setoran_sampah')
        ->join('detail_setoran', 'setoran_sampah.id', '=', 'detail_setoran.setoran_sampah_id')
        ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
        ->where('setoran_sampah.user_id', $userId)
        ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
        ->where('setoran_sampah.status_setoran', 'Selesai')
        ->where('setoran_sampah.tanggal_setoran', '>=', $startDate->format('Y-m-d'));

    // Filter berdasarkan jenis sampah jika dipilih
    if ($jenisSampah) {
        if ($jenisSampah === 'kering') {
            $query->where('katalog_sampah.kategori_sampah', 1);
        } else {
            $query->where('katalog_sampah.kategori_sampah', 2);
        }
    }

    // Group by berdasarkan periode
    if ($groupBy === 'date') {
        $trenData = $query
            ->select(
                DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m-%d') as periode"),
                DB::raw('SUM(detail_setoran.saldo) as total_saldo')
            )
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    } elseif ($groupBy === 'week') {
        $trenData = $query
            ->select(
                DB::raw("CONCAT(YEAR(setoran_sampah.tanggal_setoran), '-', WEEK(setoran_sampah.tanggal_setoran)) as periode"),
                DB::raw('SUM(detail_setoran.saldo) as total_saldo')
            )
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    } else {
        $trenData = $query
            ->select(
                DB::raw("DATE_FORMAT(setoran_sampah.tanggal_setoran, '%Y-%m') as periode"),
                DB::raw('SUM(detail_setoran.saldo) as total_saldo')
            )
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();
    }

    // Format label periode yang lebih user-friendly
    return $trenData->map(function($item) use ($periode, $groupBy) {
        if ($groupBy === 'date') {
            $date = Carbon::parse($item->periode);
            $item->label = $date->format('D'); // Format hari (Sen, Sel, dll)
        } elseif ($groupBy === 'week') {
            // Extract year and week number
            list($year, $week) = explode('-', $item->periode);
            $item->label = "Minggu-{$week}";
        } else {
            $date = Carbon::parse($item->periode . '-01');
            $item->label = $date->format('M'); // Format bulan (Jan, Feb, dll)
        }
        return $item;
    });
  }
}

