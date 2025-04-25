<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankSampah extends Model
{
    use HasFactory;

    protected $table = 'bank_sampah';

    protected $fillable = [
        'nama_bank_sampah',
        'alamat_bank_sampah',
        'deskripsi',
        'latitude',
        'longitude',
        'status_operasional',
        'jam_operasional_id',
        'tanggal_setoran',
        'jumlah_nasabah',
        'email',
        'nomor_telepon_publik',
        'foto_usaha',
        'tonase_sampah',
    ];

    protected $casts = [
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'status_operasional' => 'boolean',
        'tanggal_setoran' => 'date',
        'tonase_sampah' => 'decimal:2',
    ];

    /**
     * Get the user that owns the bank sampah (mitra).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the members of this bank sampah.
     */
    public function members()
    {
        return $this->hasMany(MemberBankSampah::class);
    }

    /**
     * Get all jam operasional for this bank sampah.
     */
    public function jamOperasional()
    {
        return $this->hasMany(JamOperasionalBankSampah::class);
    }

    /**
     * Get the katalog sampah for this bank sampah.
     */
    public function katalogSampah()
    {
        return $this->hasMany(KatalogSampah::class);
    }

    /**
     * Get the jadwal sampah for this bank sampah.
     */
    public function jadwalSampah()
    {
        return $this->hasMany(JadwalSampah::class);
    }

    /**
     * Get the setoran sampah for this bank sampah.
     */
    public function setoranSampah()
    {
        return $this->hasMany(SetoranSampah::class);
    }

    /**
     * Hitung jumlah nasabah berdasarkan data di tabel member_bank_sampah.
     */
    public function hitungJumlahNasabah()
    {
        $count = $this->members()->count();
        $this->jumlah_nasabah = $count;
        $this->save();

        return $count;
    }

    /**
     * Update total tonase sampah berdasarkan laporan bank sampah.
     */
    public function updateTotalTonase()
    {
        $total = $this->setoranSampah()->where('status_setoran', 'Selesai')->sum('total_berat');
        $this->tonase_sampah = $total;
        $this->save();

        return $total;
    }

    /**
     * Cek apakah bank sampah sedang beroperasi hari ini.
     */
    public function isBukaHariIni()
    {
        $hariIni = now()->dayOfWeek; // 0 (Minggu) hingga 6 (Sabtu)
        $jamOperasional = $this->jamOperasional()->where('day_of_week', $hariIni)->first();

        if (!$jamOperasional || !$this->status_operasional) {
            return false;
        }

        $waktuSekarang = now()->format('H:i:s');
        return $waktuSekarang >= $jamOperasional->open_time && $waktuSekarang <= $jamOperasional->close_time;
    }

    /**
     * Get the full URL for foto_usaha
     */
    public function getFotoUsahaUrlAttribute()
    {
        if (!$this->foto_usaha) {
            return null;
        }

        // Jika foto_usaha sudah berupa URL lengkap
        if (filter_var($this->foto_usaha, FILTER_VALIDATE_URL)) {
            return $this->foto_usaha;
        }

        // Jika foto_usaha adalah nama file di storage
        return url('storage/bank_sampah/' . $this->foto_usaha);
    }
}