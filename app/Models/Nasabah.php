<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nasabah extends Model
{
    use HasFactory;

    protected $table = 'nasabah';

    protected $fillable = [
        'user_id',
        'jenis_kelamin',
        'usia',
        'profesi',
        'tahu_memilah_sampah',
        'motivasi_memilah_sampah',
        'nasabah_bank_sampah',
        'kode_bank_sampah',
        'frekuensi_memilah_sampah',
        'jenis_sampah_dikelola',
    ];

    /**
     * Get the user that owns the nasabah profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the nasabah profile is complete.
     */
    public function isProfileComplete()
    {
        return !is_null($this->jenis_kelamin) &&
               !is_null($this->usia) &&
               !is_null($this->profesi) &&
               !is_null($this->tahu_memilah_sampah) &&
               !is_null($this->motivasi_memilah_sampah) &&
               !is_null($this->nasabah_bank_sampah) &&
               !is_null($this->frekuensi_memilah_sampah) &&
               !is_null($this->jenis_sampah_dikelola);
    }

    /**
     * Check if part 1 of the profile is complete.
     */
    public function isPartOneComplete()
    {
        return !is_null($this->jenis_kelamin) &&
               !is_null($this->usia) &&
               !is_null($this->profesi);
    }

    /**
     * Check if part 2 of the profile is complete.
     */
    public function isPartTwoComplete()
    {
        return !is_null($this->tahu_memilah_sampah) &&
               !is_null($this->motivasi_memilah_sampah) &&
               !is_null($this->nasabah_bank_sampah);
    }

    /**
     * Check if part 3 of the profile is complete.
     */
    public function isPartThreeComplete()
    {
        return !is_null($this->frekuensi_memilah_sampah) &&
               !is_null($this->jenis_sampah_dikelola);
    }
}