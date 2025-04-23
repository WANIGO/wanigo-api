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
        return $this->isPartOneComplete() &&
               !is_null($this->tahu_memilah_sampah) &&
               !is_null($this->motivasi_memilah_sampah) &&
               !is_null($this->nasabah_bank_sampah);
    }

    /**
     * Check if part 3 of the profile is complete.
     */
    public function isPartThreeComplete()
    {
        return $this->isPartTwoComplete() &&
               !is_null($this->frekuensi_memilah_sampah) &&
               !is_null($this->jenis_sampah_dikelola);
    }

    /**
     * Get profile completion percentage.
     *
     * @return int
     */
    public function getProfileCompletionPercentage()
    {
        $totalFields = 8; // Total fields required for complete profile
        $completedFields = 0;

        if (!is_null($this->jenis_kelamin)) $completedFields++;
        if (!is_null($this->usia)) $completedFields++;
        if (!is_null($this->profesi)) $completedFields++;
        if (!is_null($this->tahu_memilah_sampah)) $completedFields++;
        if (!is_null($this->motivasi_memilah_sampah)) $completedFields++;
        if (!is_null($this->nasabah_bank_sampah)) $completedFields++;
        if (!is_null($this->frekuensi_memilah_sampah)) $completedFields++;
        if (!is_null($this->jenis_sampah_dikelola)) $completedFields++;

        return (int) (($completedFields / $totalFields) * 100);
    }

    /**
     * Get next step for profile completion.
     *
     * @return string
     */
    public function getNextStep()
    {
        if (!$this->isPartOneComplete()) {
            return 'step1';
        }

        if (!$this->isPartTwoComplete()) {
            return 'step2';
        }

        if (!$this->isPartThreeComplete()) {
            return 'step3';
        }

        return 'complete';
    }
}