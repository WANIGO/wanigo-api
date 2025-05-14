<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provinsi extends Model
{
    use HasFactory;

    protected $table = 'provinsi';

    protected $fillable = [
        'nama_provinsi',
        'kode_provinsi',
    ];

    /**
     * Get all kabupaten/kota in this provinsi.
     */
    public function kabupatenKota(): HasMany
    {
        return $this->hasMany(KabupatenKota::class);
    }

    /**
     * Get all bank sampah in this provinsi.
     */
    public function bankSampah(): HasMany
    {
        return $this->hasMany(BankSampah::class);
    }
}