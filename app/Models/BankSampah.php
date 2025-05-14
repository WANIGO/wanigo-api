<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BankSampah extends Model
{
    use HasFactory;

    protected $table = 'bank_sampah';

    protected $fillable = [
        'tipe_bank_sampah_id',
        'nama_bank_sampah',
        'kode_admin',
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
        'provinsi_id',
        'kabupaten_kota_id',
        'kecamatan_id',
        'kelurahan_desa_id',
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
     * Get the tipe bank sampah that owns the bank sampah.
     */
    public function tipeBankSampah(): BelongsTo
    {
        return $this->belongsTo(TipeBankSampah::class, 'tipe_bank_sampah_id');
    }

    /**
     * Get the provinsi for this bank sampah.
     */
    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class);
    }

    /**
     * Get the kabupaten/kota for this bank sampah.
     */
    public function kabupatenKota(): BelongsTo
    {
        return $this->belongsTo(KabupatenKota::class);
    }

    /**
     * Get the kecamatan for this bank sampah.
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class);
    }

    /**
     * Get the kelurahan/desa for this bank sampah.
     */
    public function kelurahanDesa(): BelongsTo
    {
        return $this->belongsTo(KelurahanDesa::class);
    }

    /**
     * Get the members of this bank sampah.
     */
    public function members(): HasMany
    {
        return $this->hasMany(MemberBankSampah::class);
    }

    /**
     * Get all jam operasional for this bank sampah.
     */
    public function jamOperasional(): HasMany
    {
        return $this->hasMany(JamOperasionalBankSampah::class);
    }

    /**
     * Get the katalog sampah for this bank sampah.
     */
    public function katalogSampah(): HasMany
    {
        return $this->hasMany(KatalogSampah::class);
    }

    /**
     * Get the jadwal sampah for this bank sampah.
     */
    public function jadwalSampah(): HasMany
    {
        return $this->hasMany(JadwalSampah::class);
    }

    /**
     * Get the setoran sampah for this bank sampah.
     */
    public function setoranSampah(): HasMany
    {
        return $this->hasMany(SetoranSampah::class);
    }

    /**
     * Get the sub kategori for this bank sampah.
     */
    public function subKategori(): HasMany
    {
        return $this->hasMany(SubKategoriSampah::class);
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
     * Get alamat lengkap bank sampah.
     */
    public function getAlamatLengkapAttribute()
    {
        $alamat = $this->alamat_bank_sampah;

        if ($this->kelurahanDesa) {
            $alamat .= ', ' . $this->kelurahanDesa->nama_kelurahan_desa;
        }

        if ($this->kecamatan) {
            $alamat .= ', ' . $this->kecamatan->nama_kecamatan;
        }

        if ($this->kabupatenKota) {
            $alamat .= ', ' . $this->kabupatenKota->nama_kabupaten_kota;
        }

        if ($this->provinsi) {
            $alamat .= ', ' . $this->provinsi->nama_provinsi;
        }

        return $alamat;
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