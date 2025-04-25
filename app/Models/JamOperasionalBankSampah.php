<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class JamOperasionalBankSampah extends Model
{
    use HasFactory;

    protected $table = 'jam_operasional_bank_sampah';

    protected $fillable = [
        'bank_sampah_id',
        'day_of_week',
        'open_time',
        'close_time',
    ];

    protected $casts = [
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
    ];

    /**
     * Get the bank sampah associated with this jam operasional.
     */
    public function bankSampah()
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Get day name in Indonesian.
     */
    public function getDayNameAttribute()
    {
        $days = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        return $days[$this->day_of_week] ?? '';
    }

    /**
     * Cek apakah bank sampah sedang buka pada waktu tertentu.
     *
     * @param Carbon|null $waktu
     * @return bool
     */
    public function isBuka(Carbon $waktu = null)
    {
        if (!$waktu) {
            $waktu = Carbon::now();
        }

        $jam = $waktu->format('H:i:s');
        return $jam >= $this->open_time && $jam <= $this->close_time;
    }

    /**
     * Format jam operasional untuk tampilan.
     *
     * @return string
     */
    public function getJamOperasionalFormatAttribute()
    {
        return Carbon::parse($this->open_time)->format('H:i') . ' - ' .
               Carbon::parse($this->close_time)->format('H:i');
    }
}