<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class JadwalSampah extends Model
{
    use HasFactory;

    protected $table = 'jadwal_sampah';

    protected $fillable = [
        'user_id',
        'bank_sampah_id',
        'tipe_jadwal_id',
        'frekuensi',
        'waktu_mulai',
        'tanggal_mulai',
        'status',
        'nomor_urut',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime:H:i',
        'tanggal_mulai' => 'date',
    ];

    /**
     * Konstanta untuk frekuensi
     */
    const FREKUENSI_HARIAN = 'harian';
    const FREKUENSI_MINGGUAN = 'mingguan';
    const FREKUENSI_BULANAN = 'bulanan';

    /**
     * Konstanta untuk status
     */
    const STATUS_BELUM_SELESAI = 'belum selesai';
    const STATUS_SELESAI = 'selesai';
    const STATUS_BERLANGSUNG = 'berlangsung';

    /**
     * Get the user that owns the jadwal.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bank sampah for this jadwal.
     */
    public function bankSampah()
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Get the tipe jadwal for this jadwal.
     */
    public function tipeJadwal()
    {
        return $this->belongsTo(TipeJadwalSampah::class, 'tipe_jadwal_id');
    }

    /**
     * Scope a query to only include pemilahan jadwal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePemilahan($query)
    {
        return $query->where('tipe_jadwal_id', TipeJadwalSampah::PEMILAHAN);
    }

    /**
     * Scope a query to only include setoran jadwal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSetoran($query)
    {
        return $query->where('tipe_jadwal_id', TipeJadwalSampah::SETORAN);
    }

    /**
     * Scope a query to only include rencana setoran jadwal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRencanaSetoran($query)
    {
        return $query->where('tipe_jadwal_id', TipeJadwalSampah::RENCANA_SETORAN);
    }

    /**
     * Check if the jadwal is pemilahan.
     *
     * @return bool
     */
    public function isPemilahan()
    {
        return $this->tipe_jadwal_id == TipeJadwalSampah::PEMILAHAN;
    }

    /**
     * Check if the jadwal is setoran.
     *
     * @return bool
     */
    public function isSetoran()
    {
        return $this->tipe_jadwal_id == TipeJadwalSampah::SETORAN;
    }

    /**
     * Check if the jadwal is rencana setoran.
     *
     * @return bool
     */
    public function isRencanaSetoran()
    {
        return $this->tipe_jadwal_id == TipeJadwalSampah::RENCANA_SETORAN;
    }

    /**
     * Mark jadwal as completed.
     *
     * @return bool
     */
    public function markAsCompleted()
    {
        $this->status = self::STATUS_SELESAI;
        return $this->save();
    }

    /**
     * Format waktu untuk tampilan.
     *
     * @return string
     */
    public function getWaktuMulaiFormatAttribute()
    {
        return Carbon::parse($this->waktu_mulai)->format('H:i');
    }

    /**
     * Format tanggal untuk tampilan.
     *
     * @return string
     */
    public function getTanggalMulaiFormatAttribute()
    {
        return Carbon::parse($this->tanggal_mulai)->format('d M Y');
    }

    /**
     * Get jadwal title for display.
     *
     * @return string
     */
    public function getTitleAttribute()
    {
        $tipeText = $this->tipeJadwal->tipe_jadwal;
        return $tipeText . ' #' . $this->nomor_urut;
    }
}