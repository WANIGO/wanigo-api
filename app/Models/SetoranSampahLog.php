<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SetoranSampahLog extends Model
{
    use HasFactory;

    protected $table = 'setoran_sampah_log';

    protected $fillable = [
        'setoran_sampah_id',
        'status_setoran',
        'tanggal_status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_status' => 'date',
    ];

    /**
     * Get the setoran sampah that owns the log.
     */
    public function setoranSampah()
    {
        return $this->belongsTo(SetoranSampah::class);
    }

    /**
     * Scope a query to only include logs with status Pengajuan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePengajuan($query)
    {
        return $query->where('status_setoran', SetoranSampah::STATUS_PENGAJUAN);
    }

    /**
     * Scope a query to only include logs with status Diproses.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDiproses($query)
    {
        return $query->where('status_setoran', SetoranSampah::STATUS_DIPROSES);
    }

    /**
     * Scope a query to only include logs with status Selesai.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSelesai($query)
    {
        return $query->where('status_setoran', SetoranSampah::STATUS_SELESAI);
    }

    /**
     * Scope a query to only include logs with status Dibatalkan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDibatalkan($query)
    {
        return $query->where('status_setoran', SetoranSampah::STATUS_DIBATALKAN);
    }

    /**
     * Get formatted tanggal status.
     *
     * @return string
     */
    public function getTanggalStatusFormatAttribute()
    {
        return $this->tanggal_status->format('d M Y H:i');
    }
}