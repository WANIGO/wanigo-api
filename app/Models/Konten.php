<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Konten extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'kontens';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'modul_id',
        'tipe_konten',
        'judul_konten',
        'deskripsi',
        'urutan',
        'durasi',
        'poin',
    ];

    /**
     * Mendapatkan modul terkait dengan konten ini.
     */

    /**
     * Mendapatkan semua transaksi poin terkait dengan konten ini.
     */
    public function pointTransactions()
    {
        return $this->morphMany(PointTransaction::class, 'reference');
    }
    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id');
    }

    /**
     * Mendapatkan konten video jika tipe konten adalah 'video'.
     */
    public function kontenVideo(): HasOne
    {
        return $this->hasOne(KontenVideo::class, 'konten_id');
    }

    /**
     * Mendapatkan konten artikel jika tipe konten adalah 'artikel'.
     */
    public function kontenArtikel(): HasOne
    {
        return $this->hasOne(KontenArtikel::class, 'konten_id');
    }

    /**
     * Mendapatkan semua gambar terkait dengan konten ini.
     */
    public function kontenImages(): HasMany
    {
        return $this->hasMany(KontenImage::class, 'konten_id');
    }

    /**
     * Mendapatkan kemajuan pengguna untuk konten ini.
     */
    public function userProgress()
    {
        return $this->hasMany(UserProgress::class, 'konten_id');
    }

    /**
     * Mendapatkan kemajuan pengguna tertentu untuk konten ini.
     *
     * @param int $userId
     * @return \App\Models\UserProgress|null
     */
    public function getProgressForUser(int $userId)
    {
        return $this->userProgress()->where('user_id', $userId)->first();
    }

    /**
     * Memeriksa apakah konten sudah selesai untuk pengguna tertentu.
     *
     * @param int $userId
     * @return bool
     */
    public function isCompletedByUser(int $userId): bool
    {
        $progress = $this->getProgressForUser($userId);

        return $progress && $progress->status;
    }

    /**
     * Mendapatkan persentase kemajuan untuk pengguna tertentu.
     *
     * @param int $userId
     * @return float
     */
    public function getProgressPercentage(int $userId): float
    {
        $progress = $this->getProgressForUser($userId);

        return $progress ? $progress->progress : 0;
    }

    /**
     * Memperbarui kemajuan pengguna untuk konten ini.
     *
     * @param int $userId
     * @param float $progressValue
     * @param bool $isCompleted
     * @return void
     */
    public function updateProgress(int $userId, float $progressValue, bool $isCompleted = false): void
    {
        $progress = $this->getProgressForUser($userId);

        if (!$progress) {
            // Buat progress baru jika belum ada
            $progress = new UserProgress([
                'user_id' => $userId,
                'konten_id' => $this->id,
                'status' => $isCompleted,
                'progress' => $progressValue,
            ]);

            if ($isCompleted) {
                $progress->completed_at = now();
            }

            $progress->save();
        } else {
            // Update progress yang sudah ada
            $progress->progress = $progressValue;

            if ($isCompleted && !$progress->status) {
                $progress->status = true;
                $progress->completed_at = now();
            }

            $progress->save();
        }

        // Update status modul jika konten selesai
        if ($isCompleted) {
            $this->modul->updateStatus($userId);
        }
    }
}
