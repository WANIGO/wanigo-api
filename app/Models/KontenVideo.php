<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KontenVideo extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'konten_videos';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'konten_id',
        'video_url',
        'durasi',
    ];

    /**
     * Mendapatkan konten terkait dengan video ini.
     */
    public function konten(): BelongsTo
    {
        return $this->belongsTo(Konten::class, 'konten_id');
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
     * Mendapatkan durasi video dalam format yang mudah dibaca.
     *
     * @return string
     */
    public function getFormattedDuration(): string
    {
        $minutes = floor($this->durasi / 60);
        $seconds = $this->durasi % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Simpan video dan perbarui durasi konten induk.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = []): bool
    {
        $result = parent::save($options);

        // Update durasi pada konten induk
        if ($result && $this->konten) {
            $this->konten->durasi = $this->durasi;
            $this->konten->save();
        }

        return $result;
    }
}