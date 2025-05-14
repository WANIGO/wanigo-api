<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LaporanSampah extends Model
{
    use HasFactory;

    /**
     * Mendapatkan laporan setoran sampah per kategori untuk bank sampah tertentu.
     *
     * @param int $bankSampahId
     * @param string $periodType day|week|month|year
     * @param string|null $startDate Format Y-m-d
     * @param string|null $endDate Format Y-m-d
     * @return array
     */
    public static function getSetoranPerKategori($bankSampahId, $periodType = 'month', $startDate = null, $endDate = null)
    {
        // Set default date range jika tidak diisi
        if (!$startDate) {
            $startDate = now()->startOfMonth()->format('Y-m-d');
        }

        if (!$endDate) {
            $endDate = now()->format('Y-m-d');
        }

        // Query untuk mengambil data berat dan nilai per kategori
        $query = DetailSetoran::join('setoran_sampah', 'detail_setoran.setoran_sampah_id', '=', 'setoran_sampah.id')
            ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
            ->join('sub_kategori_sampah', 'katalog_sampah.sub_kategori_sampah_id', '=', 'sub_kategori_sampah.id')
            ->join('kategori_sampah', 'sub_kategori_sampah.kategori_sampah_id', '=', 'kategori_sampah.id')
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai')
            ->whereBetween('setoran_sampah.tanggal_setoran', [$startDate, $endDate])
            ->select(
                'kategori_sampah.nama_kategori',
                'kategori_sampah.kode_kategori',
                DB::raw('SUM(detail_setoran.berat) as total_berat'),
                DB::raw('SUM(detail_setoran.saldo) as total_nilai')
            )
            ->groupBy('kategori_sampah.id', 'kategori_sampah.nama_kategori', 'kategori_sampah.kode_kategori');

        return $query->get();
    }

    /**
     * Mendapatkan laporan setoran sampah per sub kategori untuk bank sampah tertentu.
     *
     * @param int $bankSampahId
     * @param int|null $kategoriId Filter by kategori jika diisi
     * @param string $periodType day|week|month|year
     * @param string|null $startDate Format Y-m-d
     * @param string|null $endDate Format Y-m-d
     * @return array
     */
    public static function getSetoranPerSubKategori($bankSampahId, $kategoriId = null, $periodType = 'month', $startDate = null, $endDate = null)
    {
        // Set default date range jika tidak diisi
        if (!$startDate) {
            $startDate = now()->startOfMonth()->format('Y-m-d');
        }

        if (!$endDate) {
            $endDate = now()->format('Y-m-d');
        }

        // Query untuk mengambil data berat dan nilai per sub kategori
        $query = DetailSetoran::join('setoran_sampah', 'detail_setoran.setoran_sampah_id', '=', 'setoran_sampah.id')
            ->join('katalog_sampah', 'detail_setoran.item_sampah_id', '=', 'katalog_sampah.id')
            ->join('sub_kategori_sampah', 'katalog_sampah.sub_kategori_sampah_id', '=', 'sub_kategori_sampah.id')
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai')
            ->whereBetween('setoran_sampah.tanggal_setoran', [$startDate, $endDate]);

        // Filter by kategori jika diisi
        if ($kategoriId) {
            $query->where('sub_kategori_sampah.kategori_sampah_id', $kategoriId);
        }

        $query->select(
            'sub_kategori_sampah.nama_sub_kategori',
            'sub_kategori_sampah.kode_sub_kategori',
            DB::raw('SUM(detail_setoran.berat) as total_berat'),
            DB::raw('SUM(detail_setoran.saldo) as total_nilai')
        )
        ->groupBy('sub_kategori_sampah.id', 'sub_kategori_sampah.nama_sub_kategori', 'sub_kategori_sampah.kode_sub_kategori');

        return $query->get();
    }

    /**
     * Mendapatkan laporan trend setoran sampah untuk bank sampah tertentu.
     *
     * @param int $bankSampahId
     * @param string $groupBy day|week|month|year
     * @param string|null $startDate Format Y-m-d
     * @param string|null $endDate Format Y-m-d
     * @return array
     */
    public static function getTrendSetoran($bankSampahId, $groupBy = 'day', $startDate = null, $endDate = null)
    {
        // Set default date range jika tidak diisi
        if (!$startDate) {
            if ($groupBy == 'day') {
                $startDate = now()->subDays(30)->format('Y-m-d');
            } elseif ($groupBy == 'week') {
                $startDate = now()->subWeeks(12)->format('Y-m-d');
            } elseif ($groupBy == 'month') {
                $startDate = now()->subMonths(12)->format('Y-m-d');
            } else {
                $startDate = now()->subYears(5)->format('Y-m-d');
            }
        }

        if (!$endDate) {
            $endDate = now()->format('Y-m-d');
        }

        // Menentukan format tanggal untuk grouping
        $dateFormat = '%Y-%m-%d'; // Default untuk harian
        if ($groupBy == 'week') {
            $dateFormat = '%Y-%v'; // ISO Week
        } elseif ($groupBy == 'month') {
            $dateFormat = '%Y-%m';
        } elseif ($groupBy == 'year') {
            $dateFormat = '%Y';
        }

        // Query untuk mengambil data trend setoran
        $query = SetoranSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_setoran', 'Selesai')
            ->whereBetween('tanggal_setoran', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_FORMAT(tanggal_setoran, '{$dateFormat}') as period"),
                DB::raw('SUM(total_berat) as total_berat'),
                DB::raw('SUM(total_saldo) as total_nilai'),
                DB::raw('COUNT(*) as jumlah_setoran')
            )
            ->groupBy('period')
            ->orderBy('period', 'asc');

        return $query->get();
    }

    /**
     * Mendapatkan laporan top nasabah berdasarkan berat setoran.
     *
     * @param int $bankSampahId
     * @param int $limit Jumlah nasabah yang akan ditampilkan
     * @param string|null $startDate Format Y-m-d
     * @param string|null $endDate Format Y-m-d
     * @return array
     */
    public static function getTopNasabah($bankSampahId, $limit = 10, $startDate = null, $endDate = null)
    {
        // Set default date range jika tidak diisi
        if (!$startDate) {
            $startDate = now()->startOfMonth()->format('Y-m-d');
        }

        if (!$endDate) {
            $endDate = now()->format('Y-m-d');
        }

        // Query untuk mengambil data top nasabah
        $query = SetoranSampah::join('users', 'setoran_sampah.user_id', '=', 'users.id')
            ->where('setoran_sampah.bank_sampah_id', $bankSampahId)
            ->where('setoran_sampah.status_setoran', 'Selesai')
            ->whereBetween('setoran_sampah.tanggal_setoran', [$startDate, $endDate])
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(setoran_sampah.total_berat) as total_berat'),
                DB::raw('SUM(setoran_sampah.total_saldo) as total_nilai'),
                DB::raw('COUNT(setoran_sampah.id) as jumlah_setoran')
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_berat', 'desc')
            ->limit($limit);

        return $query->get();
    }
}