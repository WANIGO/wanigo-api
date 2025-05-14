<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelurahanDesa extends Model
{
    use HasFactory;

    protected $table = 'kelurahan_desa';

    protected $fillable = [
        'kecamatan_id',
        'nama_kelurahan_desa',
        'kode_kelurahan_desa',
        'tipe', // 'kelurahan' atau 'desa'
    ];

    /**
     * Get the kecamatan that owns this kelurahan/desa.
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class);
    }

    /**
     * Get all bank sampah in this kelurahan/desa.
     */
    public function bankSampah(): HasMany
    {
        return $this->hasMany(BankSampah::class);
    }

    /**
     * Get the kabupaten through kecamatan.
     */
    public function kabupatenKota()
    {
        return $this->hasOneThrough(
            KabupatenKota::class,
            Kecamatan::class,
            'id', // Local key on kecamatan table
            'id', // Local key on kabupaten_kota table
            'kecamatan_id', // Foreign key on kelurahan_desa table
            'kabupaten_kota_id' // Foreign key on kecamatan table
        );
    }

    /**
     * Scope a query to only include kelurahan type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKelurahan($query)
    {
        return $query->where('tipe', 'kelurahan');
    }

    /**
     * Scope a query to only include desa type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDesa($query)
    {
        return $query->where('tipe', 'desa');
    }
}