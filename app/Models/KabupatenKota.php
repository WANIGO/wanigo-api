<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KabupatenKota extends Model
{
    use HasFactory;

    protected $table = 'kabupaten_kota';

    protected $fillable = [
        'provinsi_id',
        'nama_kabupaten_kota',
        'kode_kabupaten_kota',
        'tipe', // 'kabupaten' atau 'kota'
    ];

    /**
     * Get the provinsi that owns this kabupaten/kota.
     */
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class);
    }

    /**
     * Get all kecamatan in this kabupaten/kota.
     */
    public function kecamatan(): HasMany
    {
        return $this->hasMany(Kecamatan::class);
    }

    /**
     * Get all bank sampah in this kabupaten/kota.
     */
    public function bankSampah(): HasMany
    {
        return $this->hasMany(BankSampah::class);
    }

    /**
     * Scope a query to only include kabupaten type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKabupaten($query)
    {
        return $query->where('tipe', 'kabupaten');
    }

    /**
     * Scope a query to only include kota type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKota($query)
    {
        return $query->where('tipe', 'kota');
    }
}