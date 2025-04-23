<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontenImage extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'konten_images';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'konten_id',
        'image_url',
        'caption',
        'urutan',
    ];

    /**
     * Mendapatkan konten terkait dengan gambar ini.
     */
    public function konten(): BelongsTo
    {
        return $this->belongsTo(Konten::class, 'konten_id');
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