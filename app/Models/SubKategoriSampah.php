<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubKategoriSampah extends Model
{
    use HasFactory;

    protected $table = 'sub_kategori_sampah';

    protected $fillable = [
        'bank_sampah_id',
        'kategori_sampah_id',
        'nama_sub_kategori',
        'kode_sub_kategori',
        'deskripsi',
        'icon',
        'warna',
        'urutan',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    /**
     * Mendapatkan bank sampah yang memiliki sub-kategori ini.
     */
    public function bankSampah(): BelongsTo
    {
        return $this->belongsTo(BankSampah::class, 'bank_sampah_id');
    }

    /**
     * Mendapatkan kategori sampah utama dari sub-kategori ini.
     */
    public function kategoriSampah(): BelongsTo
    {
        return $this->belongsTo(KategoriSampah::class, 'kategori_sampah_id');
    }

    /**
     * Mendapatkan item sampah dalam sub-kategori ini.
     */
    public function katalogSampah(): HasMany
    {
        return $this->hasMany(KatalogSampah::class, 'sub_kategori_sampah_id');
    }

    /**
     * Scope untuk sub-kategori aktif.
     */
    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Scope untuk sub-kategori berdasarkan kategori utama.
     */
    public function scopeKategori($query, $kategoriId)
    {
        return $query->where('kategori_sampah_id', $kategoriId);
    }

    /**
     * Scope untuk mendapatkan sub-kategori sampah kering.
     */
    public function scopeKering($query)
    {
        return $query->whereHas('kategoriSampah', function ($q) {
            $q->where('kode_kategori', KategoriSampah::KERING);
        });
    }

    /**
     * Scope untuk mendapatkan sub-kategori sampah basah.
     */
    public function scopeBasah($query)
    {
        return $query->whereHas('kategoriSampah', function ($q) {
            $q->where('kode_kategori', KategoriSampah::BASAH);
        });
    }

    /**
     * Scope untuk mengurutkan sub-kategori.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan', 'asc')->orderBy('nama_sub_kategori', 'asc');
    }

    /**
     * Periksa apakah sub-kategori ini adalah untuk sampah kering.
     */
    public function isKering(): bool
    {
        return $this->kategoriSampah->isKering();
    }

    /**
     * Periksa apakah sub-kategori ini adalah untuk sampah basah.
     */
    public function isBasah(): bool
    {
        return $this->kategoriSampah->isBasah();
    }
}