<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipeBankSampah extends Model
{
    use HasFactory;

    protected $table = 'tipe_bank_sampah';

    protected $fillable = [
        'nama_tipe',
        'kode_tipe',
        'deskripsi',
    ];

    /**
     * Get all bank sampah with this type.
     */
    public function bankSampah(): HasMany
    {
        return $this->hasMany(BankSampah::class, 'tipe_bank_sampah_id');
    }
}