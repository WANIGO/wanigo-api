<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KatalogSampah extends Model
{
    use HasFactory;

    protected $table = 'katalog_sampah';

    protected $fillable = [
        'bank_sampah_id',
        'kategori_sampah',
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
    public function bankSampah()
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Get detail setoran for this item.
     */
    public function detailSetoran()
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $kategori
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKategori($query, $kategori)
    {
        return $query->where('kategori_sampah', $kategori);
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
        return $this->kategori_sampah == 0 ? 'Kering' : 'Basah';
    }
}