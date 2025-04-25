<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberBankSampah extends Model
{
    use HasFactory;

    protected $table = 'member_bank_sampah';

    protected $fillable = [
        'user_id',
        'bank_sampah_id',
        'kode_nasabah',
        'tanggal_daftar',
        'status_keanggotaan',
        'saldo',
    ];

    protected $casts = [
        'tanggal_daftar' => 'date',
        'saldo' => 'decimal:2',
    ];

    /**
     * Get the user that owns this membership.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bank sampah that owns this membership.
     */
    public function bankSampah()
    {
        return $this->belongsTo(BankSampah::class);
    }

    /**
     * Get all jadwal sampah for this member.
     */
    public function jadwalSampah()
    {
        return $this->hasMany(JadwalSampah::class, 'user_id', 'user_id')
                    ->where('bank_sampah_id', $this->bank_sampah_id);
    }

    /**
     * Get all setoran sampah for this member.
     */
    public function setoranSampah()
    {
        return $this->hasMany(SetoranSampah::class, 'user_id', 'user_id')
                    ->where('bank_sampah_id', $this->bank_sampah_id);
    }

    /**
     * Scope a query to only include active members.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAktif($query)
    {
        return $query->where('status_keanggotaan', 'aktif');
    }

    /**
     * Generate a unique code for the member.
     *
     * @return string
     */
    public function generateKodeNasabah()
    {
        $prefix = 'NS';
        $bankId = str_pad($this->bank_sampah_id, 3, '0', STR_PAD_LEFT);
        $userId = str_pad($this->user_id, 5, '0', STR_PAD_LEFT);
        $randomChars = strtoupper(substr(md5(uniqid()), 0, 4));

        $kode = $prefix . $bankId . $userId . $randomChars;

        $this->kode_nasabah = $kode;
        $this->save();

        return $kode;
    }

    /**
     * Get saldo in formatted Rupiah.
     *
     * @return string
     */
    public function getSaldoFormatAttribute()
    {
        return 'Rp ' . number_format($this->saldo, 0, ',', '.');
    }
}