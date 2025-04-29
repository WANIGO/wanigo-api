<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtikelGallery extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'artikel_galleries';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'konten_artikel_id',
        'image_url',
        'caption',
        'urutan',
    ];

    /**
     * Mendapatkan konten artikel terkait dengan gambar galeri ini.
     */
    public function kontenArtikel(): BelongsTo
    {
        return $this->belongsTo(KontenArtikel::class, 'konten_artikel_id');
    }

    /**
     * Scope untuk mengurutkan gambar berdasarkan urutan.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan', 'asc');
    }
}