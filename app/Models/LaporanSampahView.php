<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanSampahView extends Model
{
    use HasFactory;

    protected $table = 'laporan_sampah_views';

    protected $fillable = [
        'bank_sampah_id',
        'tipe_laporan',
        'periode',
        'tanggal_mulai',
        'tanggal_akhir',
        'data_json',
        'last_generated_at',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'data_json' => 'json',
        'last_generated_at' => 'datetime',
    ];

    /**
     * Get the bank sampah that owns this report.
     */
    public function bankSampah(): BelongsTo
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Menghasilkan laporan baru atau mengambil dari cache yang sudah ada.
     *
     * @param int $bankSampahId
     * @param string $tipeLaporan
     * @param string $periode
     * @param string $tanggalMulai
     * @param string $tanggalAkhir
     * @param bool $forceRegenerate
     * @return array
     */
    public static function generateOrGetCached($bankSampahId, $tipeLaporan, $periode, $tanggalMulai, $tanggalAkhir, $forceRegenerate = false)
    {
        // Cari laporan yang sudah ada
        $existing = self::where('bank_sampah_id', $bankSampahId)
            ->where('tipe_laporan', $tipeLaporan)
            ->where('periode', $periode)
            ->where('tanggal_mulai', $tanggalMulai)
            ->where('tanggal_akhir', $tanggalAkhir)
            ->first();

        // Jika sudah ada dan masih valid (kurang dari 1 jam), gunakan yang sudah ada
        if ($existing && !$forceRegenerate && $existing->last_generated_at->diffInHours(now()) < 1) {
            return $existing->data_json;
        }

        // Generate laporan baru berdasarkan tipe
        $laporanData = [];

        if ($tipeLaporan === 'kategori') {
            $laporanData = LaporanSampah::getSetoranPerKategori($bankSampahId, $periode, $tanggalMulai, $tanggalAkhir);
        } elseif ($tipeLaporan === 'sub_kategori') {
            $laporanData = LaporanSampah::getSetoranPerSubKategori($bankSampahId, null, $periode, $tanggalMulai, $tanggalAkhir);
        } elseif ($tipeLaporan === 'trend') {
            $laporanData = LaporanSampah::getTrendSetoran($bankSampahId, $periode, $tanggalMulai, $tanggalAkhir);
        } elseif ($tipeLaporan === 'nasabah') {
            $laporanData = LaporanSampah::getTopNasabah($bankSampahId, 10, $tanggalMulai, $tanggalAkhir);
        }

        // Convert to array if it's a collection
        if (is_object($laporanData) && method_exists($laporanData, 'toArray')) {
            $laporanData = $laporanData->toArray();
        }

        // Simpan atau update laporan
        if ($existing) {
            $existing->update([
                'data_json' => $laporanData,
                'last_generated_at' => now(),
            ]);
        } else {
            self::create([
                'bank_sampah_id' => $bankSampahId,
                'tipe_laporan' => $tipeLaporan,
                'periode' => $periode,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_akhir' => $tanggalAkhir,
                'data_json' => $laporanData,
                'last_generated_at' => now(),
            ]);
        }

        return $laporanData;
    }
}