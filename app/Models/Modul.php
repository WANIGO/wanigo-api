<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modul extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'moduls';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'judul_modul',
        'deskripsi',
        'objektif_modul',
        'benefit_modul',
        'hero_image_url',
        'status',
        'jumlah_konten',
        'estimasi_waktu',
        'poin',
    ];

    /**
     * Mendapatkan semua konten yang terkait dengan modul.
     */
    public function kontens(): HasMany
    {
        return $this->hasMany(Konten::class, 'modul_id');
    }

    /**
     * Mendapatkan jumlah konten yang sudah selesai untuk pengguna tertentu.
     *
     * @param int $userId
     * @return int
     */
    public function countCompletedKontens(int $userId): int
    {
        return $this->kontens()
            ->whereHas('userProgress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', true);
            })
            ->count();
    }

    /**
     * Menghitung persentase penyelesaian modul untuk pengguna tertentu.
     *
     * @param int $userId
     * @return float
     */
    public function calculateProgress(int $userId): float
    {
        $totalKontens = $this->kontens()->count();

        if ($totalKontens === 0) {
            return 0;
        }

        $completedKontens = $this->countCompletedKontens($userId);

        return ($completedKontens / $totalKontens) * 100;
    }

    /**
     * Memeriksa apakah modul sudah selesai untuk pengguna tertentu.
     *
     * @param int $userId
     * @return bool
     */
    public function isCompleted(int $userId): bool
    {
        $totalKontens = $this->kontens()->count();
        $completedKontens = $this->countCompletedKontens($userId);

        return $totalKontens > 0 && $totalKontens === $completedKontens;
    }

    /**
     * Memperbarui status modul berdasarkan progres pengguna.
     *
     * @param int $userId
     * @return void
     */
    public function updateStatus(int $userId): void
    {
        $isCompleted = $this->isCompleted($userId);

        if ($isCompleted) {
            $this->status = 'selesai';
        } else {
            $this->status = 'belum selesai';
        }

        $this->save();
    }
}