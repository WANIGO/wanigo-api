<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kecamatan extends Model
{
    use HasFactory;

    protected $table = 'kecamatan';

    protected $fillable = [
        'kabupaten_kota_id',
        'nama_kecamatan',
        'kode_kecamatan',
    ];

    /**
     * Get the kabupaten/kota that owns this kecamatan.
     */
    public function kabupatenKota(): BelongsTo
    {
        return $this->belongsTo(KabupatenKota::class);
    }

    /**
     * Get all kelurahan/desa in this kecamatan.
     */
    public function kelurahanDesa(): HasMany
    {
        return $this->hasMany(KelurahanDesa::class);
    }

    /**
     * Get all bank sampah in this kecamatan.
     */
    public function bankSampah(): HasMany
    {
        return $this->hasMany(BankSampah::class);
    }

    /**
     * Get the provinsi through kabupaten/kota.
     */
    public function provinsi()
    {
        return $this->hasOneThrough(
            Provinsi::class,
            KabupatenKota::class,
            'id', // Local key on kabupaten_kota table
            'id', // Local key on provinsi table
            'kabupaten_kota_id', // Foreign key on kecamatan table
            'provinsi_id' // Foreign key on kabupaten_kota table
        );
    }
}