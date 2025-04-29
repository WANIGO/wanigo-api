<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KontenArtikel extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'konten_artikels';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'konten_id',
        'content',
        'thumbnail_url',
    ];

    /**
     * Mendapatkan konten terkait dengan artikel ini.
     */
    public function konten(): BelongsTo
    {
        return $this->belongsTo(Konten::class, 'konten_id');
    }

    /**
     * Mendapatkan galeri gambar terkait dengan artikel ini.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(ArtikelGallery::class, 'konten_artikel_id');
    }

    /**
     * Mendapatkan judul konten.
     *
     * @return string
     */
    public function getJudulKonten(): string
    {
        return $this->konten ? $this->konten->judul_konten : '';
    }

    /**
     * Mendapatkan deskripsi konten.
     *
     * @return string
     */
    public function getDeskripsi(): string
    {
        return $this->konten ? $this->konten->deskripsi : '';
    }

    /**
     * Mendapatkan perkiraan waktu baca dalam menit.
     *
     * @return int
     */
    public function getEstimatedReadTime(): int
    {
        // Perkiraan waktu baca berdasarkan jumlah kata
        // Rata-rata kecepatan baca orang dewasa adalah 200-250 kata per menit
        $wordCount = str_word_count(strip_tags($this->content));
        $readingTime = ceil($wordCount / 200); // Asumsi 200 kata per menit

        return max(1, $readingTime); // Minimal 1 menit
    }

    /**
     * Simpan artikel dan perbarui durasi konten induk.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = []): bool
    {
        $result = parent::save($options);

        // Update durasi pada konten induk berdasarkan perkiraan waktu baca
        if ($result && $this->konten) {
            // Konversi menit ke detik
            $this->konten->durasi = $this->getEstimatedReadTime() * 60;
            $this->konten->save();
        }

        return $result;
    }
}