<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class KategoriSampah extends Model
{
    use HasFactory;

    protected $table = 'kategori_sampah';

    protected $fillable = [
        'nama_kategori',
        'kode_kategori',
        'deskripsi',
        'icon',
    ];

    /**
     * Konstanta untuk kategori sampah
     */
    const KERING = 'kering';
    const BASAH = 'basah';

    /**
     * Mendapatkan sub-kategori untuk kategori ini.
     */
    public function subKategori(): HasMany
    {
        return $this->hasMany(SubKategoriSampah::class, 'kategori_sampah_id');
    }

    /**
     * Mendapatkan katalog sampah untuk kategori ini.
     */
    public function katalogSampah(): HasManyThrough
    {
        return $this->hasManyThrough(
            KatalogSampah::class,
            SubKategoriSampah::class,
            'kategori_sampah_id', // Foreign key di SubKategoriSampah
            'sub_kategori_sampah_id', // Foreign key di KatalogSampah
            'id', // Local key di KategoriSampah
            'id' // Local key di SubKategoriSampah
        );
    }

    /**
     * Scope untuk kategori kering.
     */
    public function scopeKering($query)
    {
        return $query->where('kode_kategori', self::KERING);
    }

    /**
     * Scope untuk kategori basah.
     */
    public function scopeBasah($query)
    {
        return $query->where('kode_kategori', self::BASAH);
    }

    /**
     * Periksa apakah ini kategori kering.
     */
    public function isKering(): bool
    {
        return $this->kode_kategori === self::KERING;
    }

    /**
     * Periksa apakah ini kategori basah.
     */
    public function isBasah(): bool
    {
        return $this->kode_kategori === self::BASAH;
    }

    /**
     * Mendapatkan kategori kering.
     */
    public static function getKering()
    {
        return static::where('kode_kategori', self::KERING)->first();
    }

    /**
     * Mendapatkan kategori basah.
     */
    public static function getBasah()
    {
        return static::where('kode_kategori', self::BASAH)->first();
    }
}