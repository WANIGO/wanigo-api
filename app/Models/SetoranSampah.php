<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SetoranSampah extends Model
{
    use HasFactory;

    protected $table = 'setoran_sampah';

    protected $fillable = [
        'user_id',
        'bank_sampah_id',
        'tanggal_setoran',
        'total_saldo',
        'total_berat',
        'status_setoran',
        'kode_setoran_sampah',
        'total_poin',
        'catatan_status_setoran',
    ];

    protected $casts = [
        'tanggal_setoran' => 'date',
        'total_saldo' => 'decimal:2',
        'total_berat' => 'decimal:2',
        'total_poin' => 'integer',
    ];

    /**
     * Konstanta untuk status setoran
     */
    const STATUS_PENGAJUAN = 'Pengajuan';
    const STATUS_DIPROSES = 'Diproses';
    const STATUS_SELESAI = 'Selesai';
    const STATUS_DIBATALKAN = 'Dibatalkan';

    /**
     * Get the user that owns the setoran sampah.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bank sampah that owns the setoran sampah.
     */
    public function bankSampah()
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Get the detail setoran for this setoran sampah.
     */
    public function detailSetoran()
    {
        return $this->hasMany(DetailSetoran::class);
    }

    /**
     * Get the setoran sampah logs for this setoran sampah.
     */
    public function setoranSampahLogs()
    {
        return $this->hasMany(SetoranSampahLog::class);
    }

    /**
     * Generate a unique kode setoran sampah.
     *
     * @return string
     */
    public function generateKodeSetoran()
    {
        $prefix = 'STR';
        $bankId = str_pad($this->bank_sampah_id, 3, '0', STR_PAD_LEFT);
        $userId = str_pad($this->user_id, 5, '0', STR_PAD_LEFT);
        $date = Carbon::now()->format('ymd');
        $randomChars = strtoupper(substr(md5(uniqid()), 0, 4));

        $kode = $prefix . $bankId . $userId . $date . $randomChars;

        $this->kode_setoran_sampah = $kode;
        $this->save();

        return $kode;
    }

    /**
     * Update total saldo and total berat from detail setoran.
     */
    public function updateTotals()
    {
        $this->total_saldo = $this->detailSetoran()->sum('saldo');
        $this->total_berat = $this->detailSetoran()->sum('berat');
        $this->total_poin = floor($this->total_saldo / 1000); // 1 poin per 1000 saldo
        $this->save();
    }

    /**
     * Update status setoran dan buat log.
     *
     * @param string $status Status baru untuk setoran
     * @param string|null $catatan Catatan untuk perubahan status
     * @return bool
     */
    public function updateStatus($status, $catatan = null)
    {
        if (!in_array($status, [self::STATUS_PENGAJUAN, self::STATUS_DIPROSES, self::STATUS_SELESAI, self::STATUS_DIBATALKAN])) {
            return false;
        }

        $oldStatus = $this->status_setoran;
        $this->status_setoran = $status;

        if ($catatan) {
            $this->catatan_status_setoran = $catatan;
        }

        $saved = $this->save();

        if ($saved) {
            // Buat log perubahan status
            SetoranSampahLog::create([
                'setoran_sampah_id' => $this->id,
                'status_setoran' => $status,
                'tanggal_status' => Carbon::now(),
                'catatan' => $catatan,
            ]);

            // Jika status menjadi Selesai, update total tonase bank sampah
            if ($status === self::STATUS_SELESAI) {
                $this->bankSampah->updateTotalTonase();
            }
        }

        return $saved;
    }

    /**
     * Scope a query to only include setoran with status Pengajuan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePengajuan($query)
    {
        return $query->where('status_setoran', self::STATUS_PENGAJUAN);
    }

    /**
     * Scope a query to only include setoran with status Diproses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDiproses($query)
    {
        return $query->where('status_setoran', self::STATUS_DIPROSES);
    }

    /**
     * Scope a query to only include setoran with status Selesai.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSelesai($query)
    {
        return $query->where('status_setoran', self::STATUS_SELESAI);
    }

    /**
     * Scope a query to only include setoran with status Dibatalkan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDibatalkan($query)
    {
        return $query->where('status_setoran', self::STATUS_DIBATALKAN);
    }

    /**
     * Get formatted total saldo in Rupiah.
     *
     * @return string
     */
    public function getTotalSaldoFormatAttribute()
    {
        return 'Rp ' . number_format($this->total_saldo, 0, ',', '.');
    }

    /**
     * Get formatted total berat in kg.
     *
     * @return string
     */
    public function getTotalBeratFormatAttribute()
    {
        return number_format($this->total_berat, 2, ',', '.') . ' kg';
    }
}