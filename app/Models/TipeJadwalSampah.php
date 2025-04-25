<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipeJadwalSampah extends Model
{
    use HasFactory;

    protected $table = 'tipe_jadwal_sampah';

    protected $fillable = [
        'tipe_jadwal',
    ];

    /**
     * Get the jadwal sampah for this tipe.
     */
    public function jadwalSampah()
    {
        return $this->hasMany(JadwalSampah::class, 'tipe_jadwal_id');
    }

    /**
     * Konstanta untuk tipe jadwal
     */
    const PEMILAHAN = 1;
    const SETORAN = 2;
    const RENCANA_SETORAN = 3;

    /**
     * Scope a query untuk pemilahan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePemilahan($query)
    {
        return $query->where('id', self::PEMILAHAN);
    }

    /**
     * Scope a query untuk setoran.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSetoran($query)
    {
        return $query->where('id', self::SETORAN);
    }

    /**
     * Scope a query untuk rencana setoran.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRencanaSetoran($query)
    {
        return $query->where('id', self::RENCANA_SETORAN);
    }
}