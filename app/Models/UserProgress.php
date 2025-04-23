<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProgress extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terkait dengan model.
     *
     * @var string
     */
    protected $table = 'user_progress';

    /**
     * Atribut yang dapat diisi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'konten_id',
        'status',
        'progress',
        'completed_at',
    ];

    /**
     * Atribut yang harus dikonversi.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
        'progress' => 'float',
        'completed_at' => 'datetime',
    ];

    /**
     * Mendapatkan pengguna terkait dengan progress ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Mendapatkan konten terkait dengan progress ini.
     */
    public function konten(): BelongsTo
    {
        return $this->belongsTo(Konten::class, 'konten_id');
    }

    /**
     * Menandai progress sebagai selesai.
     *
     * @return void
     */
    public function markAsCompleted(): void
    {
        $this->status = true;
        $this->progress = 100;
        $this->completed_at = now();
        $this->save();

        // Update status modul
        if ($this->konten && $this->konten->modul) {
            $this->konten->modul->updateStatus($this->user_id);
        }
    }

    /**
     * Update progress berdasarkan persentase.
     *
     * @param float $percentage
     * @return void
     */
    public function updateProgressPercentage(float $percentage): void
    {
        $this->progress = min(100, max(0, $percentage));

        // Jika progress mencapai 100%, tandai sebagai selesai
        if ($this->progress >= 100 && !$this->status) {
            $this->markAsCompleted();
        } else {
            $this->save();
        }
    }

    /**
     * Scope untuk mengambil progress pengguna tertentu.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope untuk mengambil progress yang sudah selesai.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope untuk mengambil progress yang belum selesai.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncomplete($query)
    {
        return $query->where('status', false);
    }
}