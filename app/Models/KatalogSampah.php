<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KatalogSampah extends Model
{
    use HasFactory;

    protected $table = 'katalog_sampah';

    protected $fillable = [
        'bank_sampah_id',
        'sub_kategori_sampah_id',
        'kategori_sampah', // Kolom lama, tetap disimpan untuk kompatibilitas
        'nama_item_sampah',
        'harga_per_kg',
        'deskripsi_item_sampah',
        'cara_pemilahan',
        'cara_pengemasahan',
        'gambar_item_sampah',
        'status_aktif',
    ];

    protected $casts = [
        'harga_per_kg' => 'decimal:2',
        'kategori_sampah' => 'integer',
        'status_aktif' => 'boolean',
    ];

    /**
     * Get the bank sampah that owns this katalog sampah.
     */
    public function bankSampah(): BelongsTo
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Get the sub kategori sampah for this item.
     */
    public function subKategori(): BelongsTo
    {
        return $this->belongsTo(SubKategoriSampah::class, 'sub_kategori_sampah_id');
    }

    /**
     * Get detail setoran for this item.
     */
    public function detailSetoran(): HasMany
    {
        return $this->hasMany(DetailSetoran::class, 'item_sampah_id');
    }

    /**
     * Scope a query to only include active sampah.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAktif($query)
    {
        return $query->where('status_aktif', true);
    }

    /**
     * Scope a query to filter by kategori.
     *
     * Compat method for old kategori field
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $kategori
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKategori($query, $kategori)
    {
        return $query->where('kategori_sampah', $kategori);
    }

    /**
     * Scope to filter by sub kategori sampah id
     */
    public function scopeSubKategori($query, $subKategoriId)
    {
        return $query->where('sub_kategori_sampah_id', $subKategoriId);
    }

    /**
     * Scope to filter items by the main kategori (kering/basah)
     */
    public function scopeByKategoriUtama($query, $kategoriKode)
    {
        return $query->whereHas('subKategori.kategoriSampah', function($q) use ($kategoriKode) {
            $q->where('kode_kategori', $kategoriKode);
        });
    }

    /**
     * Scope untuk kategori kering
     */
    public function scopeKering($query)
    {
        // Cara baru menggunakan relasi
        return $query->whereHas('subKategori.kategoriSampah', function($q) {
            $q->where('kode_kategori', KategoriSampah::KERING);
        });

        // Atau cara lama sebagai fallback
        // return $query->where('kategori_sampah', 0);
    }

    /**
     * Scope untuk kategori basah
     */
    public function scopeBasah($query)
    {
        // Cara baru menggunakan relasi
        return $query->whereHas('subKategori.kategoriSampah', function($q) {
            $q->where('kode_kategori', KategoriSampah::BASAH);
        });

        // Atau cara lama sebagai fallback
        // return $query->where('kategori_sampah', 1);
    }

    /**
     * Get formatted harga in Rupiah.
     *
     * @return string
     */
    public function getHargaFormatAttribute()
    {
        return 'Rp ' . number_format($this->harga_per_kg, 0, ',', '.');
    }

    /**
     * Get kategori sampah text
     *
     * @return string
     */
    public function getKategoriSampahTextAttribute()
    {
        // Periksa apakah telah migrasi ke sistem baru
        if ($this->subKategori && $this->subKategori->kategoriSampah) {
            return $this->subKategori->kategoriSampah->nama_kategori;
        }

        // Fallback ke sistem lama
        return $this->kategori_sampah == 0 ? 'Sampah Kering' : 'Sampah Basah';
    }

    /**
     * Get nama sub kategori
     *
     * @return string
     */
    public function getSubKategoriTextAttribute()
    {
        return $this->subKategori ? $this->subKategori->nama_sub_kategori : '';
    }
}